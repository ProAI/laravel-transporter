<?php

namespace ProAI\Transporter\Loaders;

use GraphQL\Deferred;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Traits\Macroable;
use ProAI\Transporter\ModelCache;

class Loader
{
    use Macroable;

    /**
     * The class name of the Eloquent model.
     *
     * @var string
     */
    protected string $class;

    /**
     * The constraints for the relation.
     *
     * @var \Closure|null
     */
    protected mixed $constraints;

    /**
     * The keys that should be loaded.
     *
     * @var array
     */
    protected array $keys;

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
     * @param  \Closure|null  $constraints
     * @return void
     */
    public function __construct(string $class, mixed $constraints = null)
    {
        $this->class = $class;
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
     * Load model by key.
     *
     * @param  mixed  $key
     * @return \GraphQL\Deferred
     */
    public function asyncFind(mixed $key): Deferred
    {
        return $this->asyncFindBy($this->createModel()->getQualifiedKeyName(), $key);
    }

    /**
     * Load model by key or fail.
     *
     * @param  mixed  $key
     * @return \GraphQL\Executor\Promise\Adapter\SyncPromise
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function asyncFindOrFail(mixed $key): SyncPromise
    {
        return $this->asyncFind($key)->then(function (mixed $result): mixed {
            if (is_null($result)) {
                throw (new ModelNotFoundException)->setModel($this->class);
            }

            return $result;
        });
    }

    /**
     * Load model by key column.
     *
     * @param  string  $column
     * @param  mixed  $key
     * @return \GraphQL\Deferred
     */
    public function asyncFindBy(string $column, mixed $key): Deferred
    {
        $callback = function (mixed $item) use ($column, $key) {
            // Find by untransformed attribute.
            $attributes = $item->getAttributes();

            $attributeKey = last(explode('.', $column));

            return isset($attributes[$attributeKey]) && $attributes[$attributeKey] == $key;
        };

        $cached = $this->cache->get($this->class, $callback);

        if (! $cached) {
            $this->batch($column, $key);
        }

        return new Deferred(function () use ($cached, $callback) {
            if ($cached) {
                return $cached;
            }

            $this->dispatch();

            return $this->cache->get($this->class, $callback);
        });
    }

    /**
     * Batch column and key.
     *
     * @param  string  $column
     * @param  mixed  $key
     * @return void
     */
    protected function batch(string $column, mixed $key): void
    {
        if (! isset($this->keys[$column])) {
            $this->keys[$column] = [];
        }

        $this->keys[$column][] = $key;
    }

    /**
     * Dispatch loading of added keys.
     *
     * @return void
     */
    public function dispatch(): void
    {
        // There are no items to resolve, so just return.
        if (count($this->keys) === 0) {
            return;
        }

        $query = $this->createModel()->newQuery();

        if ($constraints = $this->constraints) {
            $constraints($query);
        }

        foreach ($this->keys as $column => $value) {
            if (count($value) === 1) {
                $query->where($column, $value[0]);
            } else {
                $query->whereIn($column, $value);
            }
        }

        $items = $query->get();

        foreach ($items as $item) {
            $this->cache->add($item);
        }

        $this->flush();
    }

    /**
     * Flush keys.
     *
     * @return void
     */
    protected function flush(): void
    {
        $this->keys = [];
    }

    /**
     * Create a new instance of the model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function createModel(): Model
    {
        $class = '\\'.ltrim($this->class, '\\');

        return new $class;
    }
}
