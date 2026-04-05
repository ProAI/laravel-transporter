<?php

namespace ProAI\Transporter\Loaders;

use ProAI\Transporter\ModelCache;

class RelationLoaderRepository
{
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
     * The relation loader instance.
     *
     * @var \ProAI\Transporter\Loaders\RelationLoader
     */
    protected RelationLoader $relationLoader;

    /**
     * The relation loader instances by function.
     *
     * @var \ProAI\Transporter\Loaders\AggregateLoader[]
     */
    protected array $aggregateLoaders = [];

    /**
     * The cached Eloquent models.
     *
     * @var \ProAI\Transporter\ModelCache
     */
    protected ModelCache $cache;

    /**
     * Create a new composed loader instance.
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
     * Get the relation loader instance.
     *
     * @return \ProAI\Transporter\Loaders\RelationLoader
     */
    public function getRelationLoader(): RelationLoader
    {
        if (! isset($this->relationLoader)) {
            $this->relationLoader = (new RelationLoader(
                $this->class,
                $this->name,
                $this->constraints
            ))->setCache($this->cache);
        }

        return $this->relationLoader;
    }

    /**
     * Get an aggregate loader instance.
     *
     * @param  string  $function
     * @param  string  $column
     * @return \ProAI\Transporter\Loaders\AggregateLoader
     */
    public function getAggregateLoader(string $function, string $column): AggregateLoader
    {
        $key = $function.':'.$column;

        if (! isset($this->aggregateLoaders[$key])) {
            $this->aggregateLoaders[$key] = new AggregateLoader(
                $this->class,
                $this->name,
                $column,
                $function,
                $this->constraints
            );
        }

        return $this->aggregateLoaders[$key];
    }
}
