<?php

namespace ProAI\Transporter\Type\Visitors;

use GraphQL\Type\Definition\FieldDefinition;
use ProAI\Transporter\ArgumentBag;
use ProAI\Transporter\Type\FieldResolverAdapter;

class ResolverVisitor
{
    /**
     * Visit field definition.
     *
     * @param  \GraphQL\Type\Definition\FieldDefinition  $field
     * @param  \ProAI\Transporter\ArgumentBag  $args
     * @return void
     */
    public function visitFieldDefinition(FieldDefinition $field, ArgumentBag $args): void
    {
        $resolver = $this->determineClassName($args->get('class'));

        FieldResolverAdapter::forField($field, $resolver);
    }

    /**
     * Determine the class name.
     *
     * @param  string  $class
     * @return string
     */
    protected function determineClassName(string $class): string
    {
        return str_replace('/', '\\', $class);
    }
}
