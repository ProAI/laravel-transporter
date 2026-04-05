<?php

namespace ProAI\Transporter;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;

/** @phpstan-consistent-constructor */
class Shield extends Response
{
    /**
     * The whitelisted model attributes.
     *
     * @var array
     */
    protected ?array $whitelistedAttributes = null;

    /**
     * The blacklisted model attributes.
     *
     * @var array|null
     */
    protected ?array $blacklistedAttributes = null;

    /**
     * The whitelisted model relations.
     *
     * @var array|null
     */
    protected ?array $whitelistedRelations = null;

    /**
     * The blacklisted model relations.
     *
     * @var array|null
     */
    protected ?array $blacklistedRelations = null;

    /**
     * Indicates if shields have been disabled.
     *
     * @var bool
     */
    protected static bool $disabled = false;

    /**
     * Create a new shield instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct(false);
    }

    /**
     * Create a new shield instance by whitelist.
     *
     * @param  array  $attributes
     * @param  array  $relations
     * @return static
     */
    public static function whitelist(array $attributes, array $relations = []): static
    {
        return (new static)->setWhitelisted($attributes, $relations);
    }

    /**
     * Create a new shield instance by blacklist.
     *
     * @param  array  $attributes
     * @param  array  $relations
     * @return static
     */
    public static function blacklist(array $attributes, array $relations = []): static
    {
        return (new static)->setBlacklisted($attributes, $relations);
    }

    /**
     * Set the whitelisted attributes and relations.
     *
     * @param  array  $attributes
     * @param  array  $relations
     * @return $this
     */
    public function setWhitelisted(array $attributes, array $relations = []): static
    {
        $this->whitelistedAttributes = $attributes;
        $this->whitelistedRelations = $relations;

        return $this;
    }

    /**
     * Set the blacklisted attributes and relations.
     *
     * @param  array  $attributes
     * @param  array  $relations
     * @return $this
     */
    public function setBlacklisted(array $attributes, array $relations = []): static
    {
        $this->blacklistedAttributes = $attributes;
        $this->blacklistedRelations = $relations;

        return $this;
    }

    /**
     * Determine if the response was allowed for a model attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function allowedForAttribute(string $key): bool
    {
        if (static::$disabled) {
            return true;
        }

        if (isset($this->whitelistedAttributes)) {
            return in_array($key, $this->whitelistedAttributes);
        }

        if (isset($this->blacklistedAttributes)) {
            return ! in_array($key, $this->blacklistedAttributes);
        }

        return true;
    }

    /**
     * Determine if the response was allowed for a model relation.
     *
     * @param  string  $key
     * @return bool
     */
    public function allowedForRelation(string $key): bool
    {
        if (static::$disabled) {
            return true;
        }

        if (isset($this->whitelistedRelations)) {
            return in_array($key, $this->whitelistedRelations);
        }

        if (isset($this->blacklistedRelations)) {
            return ! in_array($key, $this->blacklistedRelations);
        }

        return true;
    }

    /**
     * Determine if the response was denied for given attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function deniedForAttribute(string $key): bool
    {
        return ! $this->allowedForAttribute($key);
    }

    /**
     * Determine if the response was denied for given relation.
     *
     * @param  string  $key
     * @return bool
     */
    public function deniedForRelation(string $key): bool
    {
        return ! $this->allowedForRelation($key);
    }

    /**
     * Set response message.
     *
     * @param  string  $message
     * @return void
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    /**
     * Set response code.
     *
     * @param  mixed  $code
     * @return void
     */
    public function setCode(mixed $code): void
    {
        $this->code = $code;
    }

    /**
     * Throw authorization exception if response was denied for attribute.
     *
     * @param  string  $key
     * @return static
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorizeForAttribute(string $key): static
    {
        if ($this->deniedForAttribute($key)) {
            $message = empty($this->message())
                ? 'This action is unauthorized for attribute "'.$key.'".'
                : $this->message();

            throw (new AuthorizationException($message, $this->code()))
                ->setResponse($this);
        }

        return $this;
    }

    /**
     * Throw authorization exception if response was denied for relation.
     *
     * @param  string  $key
     * @return static
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorizeForRelation(string $key): static
    {
        if ($this->deniedForRelation($key)) {
            $message = empty($this->message())
                ? 'This action is unauthorized for relation "'.$key.'".'
                : $this->message();

            throw (new AuthorizationException($message, $this->code()))
                ->setResponse($this);
        }

        return $this;
    }

    /**
     * Disable shields for given callback.
     *
     * @param  callable  $callback
     * @return mixed
     */
    public static function disableFor(callable $callback): mixed
    {
        static::$disabled = true;

        try {
            return $callback();
        } finally {
            static::$disabled = false;
        }
    }
}
