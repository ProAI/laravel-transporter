<?php

namespace ProAI\Transporter\Type\Visitors;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\UnionType;
use ProAI\Transporter\ArgumentBag;
use ProAI\Transporter\Type\TypeResolverAdapter;

class TypeResolverVisitor
{
    /**
     * Visit interface type.
     *
     * @param  \GraphQL\Type\Definition\InterfaceType  $type
     * @param  \ProAI\Transporter\ArgumentBag  $args
     * @return void
     */
    public function visitInterface(InterfaceType $type, ArgumentBag $args): void
    {
        $class = $this->determineClassName($args->get('class'));

        $this->setTypeResolver($type, $class);
    }

    /**
     * Visit union type.
     *
     * @param  \GraphQL\Type\Definition\UnionType  $type
     * @param  \ProAI\Transporter\ArgumentBag  $args
     * @return void
     */
    public function visitUnion(UnionType $type, ArgumentBag $args): void
    {
        $class = $this->determineClassName($args->get('class'));

        $this->setTypeResolver($type, $class);
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

    /**
     * Set caster on type.
     *
     * @param  \GraphQL\Type\Definition\InterfaceType|\GraphQL\Type\Definition\UnionType  $type
     * @param  string  $class
     * @return void
     */
    protected function setTypeResolver(InterfaceType|UnionType $type, string $class): void
    {
        TypeResolverAdapter::forType($type, $class);
    }
}
