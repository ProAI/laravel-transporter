<?php

namespace ProAI\Transporter;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Traits\ForwardsCalls;
use ProAI\Transporter\Contracts\HasParent;

class ReverseRelation extends Relation
{
    use ForwardsCalls;

    /**
     * The child model instance of the relation.
     *
     * @var \Illuminate\Database\Eloquent\Model&\ProAI\Transporter\Contracts\HasParent
     */
    protected Model&HasParent $child;

    /**
     * The name of the relationship.
     *
     * @var string
     */
    protected string $relationName;

    /**
     * The name of the inverse relation on the parent model.
     *
     * @var string
     */
    protected string $inverseRelationName;

    /**
     * The inverse relation.
     *
     * @var \Illuminate\Database\Eloquent\Relations\Relation
     */
    protected Relation $relation;

    /**
     * Create a new depends on relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model&\ProAI\Transporter\Contracts\HasParent  $child
     * @param  string  $inverseRelationName
     * @param  string  $relationName
     * @return void
     */
    public function __construct(Builder $query, Model&HasParent $child, string $inverseRelationName, string $relationName)
    {
        $this->relationName = $relationName;
        $this->inverseRelationName = $inverseRelationName;

        // In the underlying base relationship class, this variable is referred to as
        // the "parent" since most relationships are not inversed. But, since this
        // one is we will create a "child" variable for much better readability.
        $this->child = $child;

        $this->query = $query;
        $this->related = $query->getModel();

        $this->relation = $this->createBaseRelation();
    }

    /**
     * Create the relation based on the inverse relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     *
     * @throws \Exception
     */
    protected function createBaseRelation(): Relation
    {
        $relation = $this->related->{$this->inverseRelationName}();

        if ($relation instanceof MorphTo) {
            return new MorphOne(
                $this->query,
                $this->child,
                $relation->getMorphType(),
                $relation->getForeignKeyName(),
                $relation->getOwnerKeyName() ?? $this->child->getKeyName()
            );
        }

        if ($relation instanceof MorphOneOrMany) {
            return new MorphTo(
                $this->query,
                $this->child,
                $relation->getForeignKeyName(),
                $relation->getLocalKeyName(),
                $relation->getMorphType(),
                $this->relationName
            );
        }

        if ($relation instanceof BelongsTo) {
            return new HasOne(
                $this->query,
                $this->child,
                $relation->getForeignKeyName(),
                $relation->getOwnerKeyName()
            );
        }

        if ($relation instanceof HasOneOrMany) {
            return new BelongsTo(
                $this->query,
                $this->child,
                $relation->getForeignKeyName(),
                $relation->getLocalKeyName(),
                $this->relationName
            );
        }

        throw new Exception('Relation type not supported.');
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints(): void
    {
        $this->relation->addConstraints();
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models): void
    {
        $this->relation->addEagerConstraints($models);
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array  $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, mixed $relation): array
    {
        return $this->relation->initRelation($models, $relation);
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array  $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, mixed $relation): array
    {
        return $this->relation->match($models, $results, $relation);
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, mixed $columns = ['*']): Builder
    {
        return $this->relation->getRelationExistenceQuery($query, $parentQuery, $columns);
    }

    /**
     * Get a relationship join table hash.
     *
     * @param  bool  $incrementJoinCount
     * @return string
     */
    public function getRelationCountHash(mixed $incrementJoinCount = true): string
    {
        return $this->relation->getRelationCountHash($incrementJoinCount);
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults(): mixed
    {
        return $this->relation->getResults();
    }

    /**
     * Get the child of the relationship.
     *
     * @return \Illuminate\Database\Eloquent\Model&\ProAI\Transporter\Contracts\HasParent
     */
    public function getChild(): Model&HasParent
    {
        return $this->child;
    }

    /**
     * Get the name of the inverse relationship.
     *
     * @return string
     */
    public function getInverseRelationName(): string
    {
        return $this->inverseRelationName;
    }

    /**
     * Get the base relationship instance.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function getBaseRelation(): Relation
    {
        return $this->relation;
    }

    /**
     * Get the name of the relationship.
     *
     * @return string
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }

    /**
     * Handle dynamic method calls into the relation.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call(mixed $method, mixed $parameters): mixed
    {
        return $this->forwardCallTo($this->relation, $method, $parameters);
    }
}
