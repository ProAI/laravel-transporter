<?php

namespace ProAI\Transporter\Type\Visitors;

use GraphQL\Type\Definition\FieldDefinition;
use ProAI\Transporter\ArgumentBag;
use ProAI\Transporter\Resolvers\CountResolver;
use ProAI\Transporter\Type\FieldResolverAdapter;

class CountVisitor
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
        FieldResolverAdapter::forField($field, CountResolver::class);
    }
}
