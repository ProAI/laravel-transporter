<?php

namespace ProAI\Transporter\Loaders;

use Closure;
use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;

class AggregateLoader
{
    use Macroable;

    /**
     * The class name of the model.
     *
     * @var string
     */
    protected string $class;

    /**
     * The name of the attribute.
     *
     * @var string
     */
    protected string $name;

    /**
     * The aggregate column name.
     *
     * @var string
     */
    protected string $column;

    /**
     * The aggregate function name.
     *
     * @var string
     */
    protected ?string $function;

    /**
     * The aggregate constraints.
     *
     * @var \Closure|null
     */
    protected ?Closure $constraints;

    /**
     * The attribute key of the aggregate.
     *
     * @var string
     */
    protected string $attributeKey;

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
     * Create a new aggregate loader instance.
     *
     * @param  string  $class
     * @param  string  $name
     * @param  string  $column
     * @param  string  $function
     * @param  \Closure  $constraints
     * @return void
     */
    public function __construct(string $class, string $name, string $column, ?string $function = null, ?Closure $constraints = null)
    {
        $this->class = $class;
        $this->name = $name;
        $this->column = $column;
        $this->function = $function;
        $this->constraints = $constraints;

        $this->attributeKey = $this->getAttributeKey($name, $column, $function);

        $this->flush();
    }

    /**
     * Get the attribute key of the aggregate.
     *
     * @param  string  $name
     * @param  string  $column
     * @param  string  $function
     * @return string
     */
    public function getAttributeKey(string $name, string $column, ?string $function): string
    {
        return Str::snake(
            preg_replace('/[^[:alnum:][:space:]_]/u', '', "$name $function $column")
        );
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
            $shield->authorizeForAttribute($this->attributeKey);
        }

        if (! $item instanceof $this->class) {
            throw new InvalidArgumentException('Model given must be an instance of "'.$this->class.'"');
        }

        // Clone item, so we do not mix up the loaded aggregates.
        $item = clone $item;

        $itemKey = $item->getKey();

        $cached = $this->loadUsingResultsCache($item);

        if (! $cached) {
            $this->items->add($item);
        }

        return new Deferred(function () use ($item, $cached) {
            if (! $cached) {
                $this->dispatch();
            }

            return $item->getAttributeValue($this->attributeKey);
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

        if (! isset($this->results[$itemKey])) {
            return false;
        }

        $item->setAttribute(
            $this->attributeKey,
            $this->results[$itemKey]
        );

        return true;
    }

    /**
     * Dispatch loading of added items.
     *
     * @return void
     */
    public function dispatch(): void
    {
        // There are no items to resolve, so just return.
        if ($this->items->count() === 0) {
            return;
        }

        // Lazy load aggregate values.
        $this->loadAggregate();

        foreach ($this->items as $item) {
            $result = $item->getAttributeValue($this->attributeKey);

            $this->results[$item->getKey()] = $result;
        }

        $this->flush();
    }

    /**
     * Lazy load aggregate values.
     *
     * @return void
     */
    protected function loadAggregate(): void
    {
        $items = $this->items;

        $relation = $this->constraints
            ? [$this->name => $this->constraints]
            : $this->name;

        $items->loadAggregate($relation, $this->column, $this->function);
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
