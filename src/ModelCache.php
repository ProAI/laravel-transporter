<?php

namespace ProAI\Transporter;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ModelCache
{
    /**
     * The loaded Eloquent model instances.
     *
     * @var array
     */
    protected array $cache = [];

    /**
     * Add an Eloquent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function add(Model $model): void
    {
        $cache = $this->getCacheFor(get_class($model));

        $cache->add(clone $model);
    }

    /**
     * Get the Eloquent model.
     *
     * @param  string  $class
     * @param  mixed  $key
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function get(string $class, mixed $key): ?Model
    {
        $cache = $this->getCacheFor($class);

        $model = $key instanceof Closure
            ? $cache->first($key)
            : $cache->find($key);

        return is_null($model) ? $model : clone $model;
    }

    /**
     * Get chunk for given Eloquent model class.
     *
     * @param  string  $class
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getCacheFor(string $class): Collection
    {
        if (! isset($this->cache[$class])) {
            $this->cache[$class] = new Collection;
        }

        return $this->cache[$class];
    }
}
