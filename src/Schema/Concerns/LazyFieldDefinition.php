<?php

namespace ProAI\Transporter\Schema\Concerns;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\UnresolvedFieldDefinition;

class LazyFieldDefinition extends UnresolvedFieldDefinition
{
    /**
     * Name of field definition.
     *
     * @var string
     */
    private string $name;

    /**
     * Lazy field store instance.
     *
     * @var \ProAI\Transporter\Schema\Concerns\LazyFieldStore
     */
    private LazyFieldStore $store;

    /**
     * Create a new lazy field definition instance.
     *
     * @param  string  $name
     * @param  \ProAI\Transporter\Schema\Concerns\LazyFieldStore  $store
     * @return void
     */
    public function __construct(string $name, LazyFieldStore $store)
    {
        $this->name = $name;
        $this->store = $store;
    }

    /**
     * Get name of field definition.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Resolve field definition.
     *
     * @return \GraphQL\Type\Definition\FieldDefinition
     */
    public function resolve(): FieldDefinition
    {
        return $this->store->get($this->name);
    }
}
