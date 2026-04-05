<?php

namespace ProAI\Transporter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ProAI\Transporter\Contracts\HasClientKey;

trait ShieldsAttributes
{
    /**
     * The shield instance.
     *
     * @var \ProAI\Transporter\Shield
     */
    protected $shield;

    /**
     * Set the shield instance.
     *
     * @param  \ProAI\Transporter\Shield  $key
     * @return void
     */
    public function setShield(Shield $shield)
    {
        $this->shield = $shield;
    }

    /**
     * Unset the shield instance.
     *
     * @return void
     */
    public function unsetShield()
    {
        unset($this->shield);
    }

    /**
     * Get the shield instance.
     *
     * @return \ProAI\Transporter\Shield|null
     */
    public function getShield()
    {
        return $this->shield ?? null;
    }

    /**
     * Get a plain attribute (not a relationship).
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttributeValue($key)
    {
        $this->authorizeAttribute($key);

        return parent::getAttributeValue($key);
    }

    /**
     * Get a relationship.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getRelationValue($key)
    {
        $this->authorizeRelation($key);

        return parent::getRelationValue($key);
    }

    /**
     * Authorize an attribute.
     *
     * @param  string  $key
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorizeAttribute($key)
    {
        if (! isset($this->shield)) {
            return;
        }

        // Always allow model identifier.
        if ($key === $this->getKeyName()) {
            return;
        }

        // Always allow model client identifier if set.
        if ($this instanceof HasClientKey && $key === $this->getClientKeyName()) {
            return;
        }

        // Allow attribute if requested by a relation, because relation is
        // authorized separately.
        if ($this->attributeCalledFromRelation($key)) {
            return;
        }

        $this->shield->authorizeForAttribute($key);
    }

    /**
     * Determine if the attribute was called from a relation instance.
     *
     * @param  string  $key
     * @return bool
     */
    protected function attributeCalledFromRelation($key)
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($trace as $caller) {
            if (is_a($caller['class'], Model::class, true)) {
                continue;
            }

            return is_a($caller['class'], Relation::class, true);
        }

        return false;
    }

    /**
     * Authorize a relation.
     *
     * @param  string  $key
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorizeRelation($key)
    {
        if (! isset($this->shield)) {
            return;
        }

        $this->shield->authorizeForRelation($key);
    }
}
