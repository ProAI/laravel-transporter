<?php

namespace ProAI\Transporter;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait ReversesRelationships
{
    /**
     * Define a relationship based on a parent's model relationship.
     *
     * @param  string  $related
     * @param  string  $inverseRelation
     * @param  string  $relation
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function reverseOf($related, $inverseRelation, $relation = null)
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        $instance = $this->newRelatedInstance($related);

        return $this->newReverseRelation(
            $instance->newQuery(), $this, $inverseRelation, $relation
        );
    }

    /**
     * Define a relationship based on a parent's model morph relationship.
     *
     * @param  string  $inverseRelation
     * @param  string  $relation
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function reverseOfMorph($inverseRelation, $relation = null)
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        [$type, $id] = $this->getMorphs(
            Str::snake($relation), null, null
        );

        if (is_null($class = $this->getAttributeFromArray($type)) || $class === '') {
            throw new Exception('Morph class not found.');
        }

        $related = static::getActualClassNameForMorph($class);

        $instance = $this->newRelatedInstance($related);

        return $this->newReverseRelation(
            $instance->newQuery(), $this, $inverseRelation, $relation
        );
    }

    /**
     * Instantiate a new ReverseRelation relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $child
     * @param  string  $inverseRelation
     * @param  string  $relation
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    protected function newReverseRelation(Builder $query, Model $child, $inverseRelation, $relation)
    {
        return new ReverseRelation($query, $child, $inverseRelation, $relation);
    }
}
