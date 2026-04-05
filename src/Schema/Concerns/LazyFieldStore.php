<?php

namespace ProAI\Transporter\Schema\Concerns;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\HasFieldsType;
use GraphQL\Type\Definition\UnresolvedFieldDefinition;

class LazyFieldStore
{
    /**
     * Lazily initialized.
     *
     * @var array
     */
    private array $fields;

    /**
     * Type instance.
     *
     * @var \GraphQL\Type\Definition\HasFieldsType
     */
    private HasFieldsType $type;

    /**
     * Create a new lazy field store instance.
     *
     * @param  \GraphQL\Type\Definition\HasFieldsType  $type
     * @return void
     */
    public function __construct(HasFieldsType $type)
    {
        $this->type = $type;
    }

    /**
     * Initialize fields of type.
     *
     * @return void
     */
    private function initializeFields(): void
    {
        if (isset($this->fields)) {
            return;
        }

        $this->fields = $this->type->getFields();
    }

    /**
     * Get field of type.
     *
     * @return \GraphQL\Type\Definition\FieldDefinition|\GraphQL\Type\Definition\UnresolvedFieldDefinition
     */
    public function get(string $name): FieldDefinition|UnresolvedFieldDefinition
    {
        $this->initializeFields();

        return $this->fields[$name];
    }
}
