<?php

namespace ProAI\Transporter\Loaders;

use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use ProAI\Transporter\Contracts\HasParent;
use ProAI\Transporter\ModelCache;
use ProAI\Transporter\ReverseRelation;

class RelationLoader
{
    use Macroable;

    /**
     * The class name of the model.
     *
     * @var string
     */
    protected string $class;

    /**
     * The name of the relation.
     *
     * @var string
     */
    protected string $name;

    /**
     * The constraints for the relation.
     *
     * @var \Closure|null
     */
    protected mixed $constraints;

    /**
     * The items that should be loaded.
     *
     * @var \Illuminate\Database\Eloquent\Collection
     */
    protected Collection $items;

    /**
     * The loaded results.
     *
     * @var array
     */
    protected array $results = [];

    /**
     * The cached Eloquent models.
     *
     * @var \ProAI\Transporter\ModelCache
     */
    protected ModelCache $cache;

    /**
     * Create a new relation loader instance.
     *
     * @param  string  $class
     * @param  string  $name
     * @param  \Closure  $constraints
     * @return void
     */
    public function __construct(string $class, string $name, mixed $constraints = null)
    {
        $this->class = $class;
        $this->name = $name;
        $this->constraints = $constraints;

        $this->flush();
    }

    /**
     * Set the shared instance of the model cache.
     *
     * @param  \ProAI\Transporter\ModelCache  $cache
     * @return $this
     */
    public function setCache(ModelCache $cache): static
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Get relation instance.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function getRelation(): Relation
    {
        return (new $this->class)->{$this->name}();
    }

    /**
     * Add item to loader.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $item
     * @return \GraphQL\Deferred
     */
    public function asyncLoadFrom(Model $item): Deferred
    {
        if (method_exists($item, 'getShield') && $shield = $item->getShield()) {
            $shield->authorizeForRelation($this->name);
        }

        if (! $item instanceof $this->class) {
            throw new InvalidArgumentException('Model given must be an instance of "'.$this->class.'"');
        }

        // Clone item, so we do not mix up the loaded relationships.
        $item = clone $item;

        // Check if result has already been loaded.
        $loaded = $this->loadUsingResultsCache($item) ||
                  $this->loadUsingModelCache($item);

        if (! $loaded) {
            $this->items->add($item);
        }

        return new Deferred(function () use ($item, $loaded) {
            // Recheck if result has already been loaded by another loader in
            // the meantime.
            $loaded = $loaded || $this->loadUsingModelCache($item);

            if (! $loaded) {
                $this->dispatch();
            }

            $result = $item->getRelation($this->name);

            // Set inverse relation on result.
            if ($result instanceof Collection) {
                foreach ($result as $model) {
                    $this->setInverseRelationOnResult($item, $model);
                }
            } elseif ($result instanceof Model) {
                $this->setInverseRelationOnResult($item, $result);
            }

            return $result;
        });
    }

    /**
     * Try to load relation using the results cache.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $item
     * @return bool
     */
    protected function loadUsingResultsCache(Model $item): bool
    {
        $itemKey = $item->getKey();

        if (is_null($itemKey)) {
            return false;
        }

        if (! isset($this->results[$itemKey])) {
            return false;
        }

        $item->setRelation($this->name, $this->results[$itemKey]);

        return true;
    }

    /**
     * Try to load relation using the model cache.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $item
     * @return bool
     */
    protected function loadUsingModelCache(Model $item): bool
    {
        if ($this->constraints) {
            return false;
        }

        $relation = $item->{$this->name}();

        if ($relation instanceof ReverseRelation) {
            $relation = $relation->getBaseRelation();
        }

        if (! $relation instanceof BelongsTo) {
            return false;
        }

        $foreignKey = $relation->getParentKey();

        if ($foreignKey === null) {
            $item->setRelation($this->name, null);

            return true;
        }

        $class = get_class($relation->getQuery()->getModel());

        $cached = $this->cache->get($class, $foreignKey);

        if (! $cached) {
            return false;
        }

        $item->setRelation($this->name, $cached);

        return true;
    }

    /**
     * Set inverse relation on result.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  \Illuminate\Database\Eloquent\Model  $result
     * @return void
     */
    protected function setInverseRelationOnResult(Model $parent, Model $result): void
    {
        if (! $result instanceof HasParent) {
            return;
        }

        $relation = $result->parent();

        $class = get_class($relation->getRelated());

        // Check if classes match and also check for subclass to support single
        // table inheritance packages.
        if (get_class($parent) !== $class && ! is_subclass_of($parent, $class)) {
            return;
        }

        $result->setRelation($relation->getRelationName(), $parent);
    }

    /**
     * Dispatch loading of added items.
     *
     * @return void
     */
    public function dispatch(): void
    {
        // There are no items to resolve, so just return.
        if ($this->items->isEmpty()) {
            return;
        }

        // Lazy load items.
        $relation = $this->constraints
            ? [$this->name => $this->constraints]
            : $this->name;

        $query = (new $this->class)->newQueryWithoutRelationships()->with($relation);

        $resultItems = $query->eagerLoadRelations($this->items->all());

        foreach ($resultItems as $item) {
            $itemKey = $item->getKey();

            if (is_null($itemKey)) {
                continue;
            }

            $result = $item->getRelation($this->name);

            // Cache in results cache.
            $this->results[$itemKey] = $result;

            // Cache in models cache.
            $this->cacheModels($result);
        }

        $this->flush();
    }

    /**
     * Cache result.
     *
     * @param  mixed  $result
     * @return void
     */
    protected function cacheModels(mixed $result): void
    {
        if ($result === null) {
            return;
        }

        if ($result instanceof Model) {
            $result = new Collection([$result]);
        }

        foreach ($result as $item) {
            $this->cache->add($item);
        }
    }

    /**
     * Flush items and move them to resolved items collection.
     *
     * @return void
     */
    protected function flush(): void
    {
        $this->items = new Collection;
    }
}
