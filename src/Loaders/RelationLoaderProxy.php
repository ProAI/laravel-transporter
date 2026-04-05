<?php

namespace ProAI\Transporter\Loaders;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Support\Traits\Macroable;

class RelationLoaderProxy
{
    use Macroable;

    /**
     * The item from which the relation should be loaded.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected Model $item;

    /**
     * The relation loader repository instance.
     *
     * @var \ProAI\Transporter\Loaders\RelationLoaderRepository
     */
    protected RelationLoaderRepository $repository;

    /**
     * Create a new composed loader instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $item
     * @param  \ProAI\Transporter\Loaders\RelationLoaderRepository  $repository
     * @return void
     */
    public function __construct(Model $item, RelationLoaderRepository $repository)
    {
        $this->item = $item;
        $this->repository = $repository;
    }

    /**
     * Load the relation.
     *
     * @return \GraphQL\Deferred
     */
    public function asyncLoad(): mixed
    {
        return $this->repository->getRelationLoader()->asyncLoadFrom($this->item);
    }

    /**
     * Get the relation result as collection.
     *
     * @return mixed
     */
    public function asyncGet(): mixed
    {
        return $this->asyncLoad()->then(function (mixed $result) {
            return Collection::wrap($result);
        });
    }

    /**
     * Get the first item of result.
     *
     * @return \GraphQL\Deferred
     */
    public function asyncFirst(): mixed
    {
        return $this->asyncGet()->then(function (mixed $result) {
            return $result->first();
        });
    }

    /**
     * Get the first item of result or fail.
     *
     * @return \GraphQL\Deferred
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function asyncFirstOrFail(): mixed
    {
        return $this->asyncGet()->then(function (mixed $result) {
            if ($result->isEmpty()) {
                $relation = $this->repository->getRelationLoader()->getRelation();

                throw (new ModelNotFoundException)->setModel(get_class($relation->getRelated()));
            }

            return $result->first();
        });
    }

    /**
     * Get the first result if it's the sole matching record.
     *
     * @return \GraphQL\Deferred
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \Illuminate\Database\MultipleRecordsFoundException
     */
    public function asyncSole(): mixed
    {
        return $this->asyncGet()->then(function (mixed $result) {
            if ($result->isEmpty()) {
                $relation = $this->repository->getRelationLoader()->getRelation();

                throw (new ModelNotFoundException)->setModel(get_class($relation->getRelated()));
            }

            if ($result->count() > 1) {
                throw new MultipleRecordsFoundException($result->count());
            }

            return $result->first();
        });
    }

    /**
     * Load the "count" result of the query.
     *
     * @return \GraphQL\Deferred
     */
    public function asyncCount(): mixed
    {
        return $this->asyncAggregate('count', '*');
    }

    /**
     * Load the minimum value of a given column.
     *
     * @param  string  $column
     * @return \GraphQL\Deferred
     */
    public function asyncMin(string $column): mixed
    {
        return $this->asyncAggregate('min', $column);
    }

    /**
     * Load the maximum value of a given column.
     *
     * @param  string  $column
     * @return \GraphQL\Deferred
     */
    public function asyncMax(string $column): mixed
    {
        return $this->asyncAggregate('max', $column);
    }

    /**
     * Load the sum of the values of a given column.
     *
     * @param  string  $column
     * @return \GraphQL\Deferred
     */
    public function asyncSum(string $column): mixed
    {
        return $this->asyncAggregate('sum', $column);
    }

    /**
     * Load the average of the values of a given column.
     *
     * @param  string  $column
     * @return \GraphQL\Deferred
     */
    public function asyncAvg(string $column): mixed
    {
        return $this->asyncAggregate('avg', $column);
    }

    /**
     * Alias for the "avg" method.
     *
     * @param  string  $column
     * @return \GraphQL\Deferred
     */
    public function asyncAverage(string $column): mixed
    {
        return $this->asyncAvg($column);
    }

    /**
     * Load an aggregate function on the database.
     *
     * @param  string  $column
     * @param  string  $function
     * @return \GraphQL\Deferred
     */
    public function asyncAggregate(string $function, string $column): mixed
    {
        return $this->repository->getAggregateLoader($function, $column)->asyncLoadFrom($this->item);
    }
}
