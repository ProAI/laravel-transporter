<?php

namespace ProAI\Transporter\Schema\Concerns;

use Exception;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Type\Definition\NamedType;

trait HasMutators
{
    /**
     * The user defined type mutators.
     *
     * @var \Closure[]
     */
    protected $typeMutators = [];

    /**
     * The user defined directive mutators.
     *
     * @var \Closure[]
     */
    protected $directiveMutators = [];

    /**
     * Mutate directive.
     *
     * @param  string  $name
     * @param  \Closure  $callback
     * @return void
     *
     * @throws \Exception
     */
    public function directive($name, $callback)
    {
        if (! $this->hasDirective($name)) {
            throw new Exception('Unknown directive "'.$name.'".');
        }

        $this->directiveMutators[$name] = $callback;
    }

    /**
     * Mutate scalar type.
     *
     * @param  string  $name
     * @param  \Closure  $callback
     * @return void
     *
     * @throws \Exception
     */
    public function scalar($name, $callback)
    {
        if (! isset($this->nodeMap[$name])) {
            throw new Exception('Unknown type "'.$name.'".');
        }

        if (! $this->nodeMap[$name] instanceof ScalarTypeDefinitionNode) {
            throw new Exception('Type "'.$name.'" is not of type scalar.');
        }

        $this->typeMutators[$name] = $callback;
    }

    /**
     * Mutate object type.
     *
     * @param  string  $name
     * @param  \Closure  $callback
     * @return void
     *
     * @throws \Exception
     */
    public function type($name, $callback)
    {
        if (! isset($this->nodeMap[$name])) {
            throw new Exception('Unknown type "'.$name.'".');
        }

        if (! $this->nodeMap[$name] instanceof ObjectTypeDefinitionNode) {
            throw new Exception('Type "'.$name.'" is not of type object.');
        }

        $this->typeMutators[$name] = $callback;
    }

    /**
     * Mutate interface type.
     *
     * @param  string  $name
     * @param  \Closure  $callback
     * @return void
     *
     * @throws \Exception
     */
    public function interface($name, $callback)
    {
        if (! isset($this->nodeMap[$name])) {
            throw new Exception('Unknown type "'.$name.'".');
        }

        if (! $this->nodeMap[$name] instanceof InterfaceTypeDefinitionNode) {
            throw new Exception('Type "'.$name.'" is not of type interface.');
        }

        $this->typeMutators[$name] = $callback;
    }

    /**
     * Mutate union type.
     *
     * @param  string  $name
     * @param  \Closure  $callback
     * @return void
     *
     * @throws \Exception
     */
    public function union($name, $callback)
    {
        if (! isset($this->nodeMap[$name])) {
            throw new Exception('Unknown type "'.$name.'".');
        }

        if (! $this->nodeMap[$name] instanceof UnionTypeDefinitionNode) {
            throw new Exception('Type "'.$name.'" is not of type union.');
        }

        $this->typeMutators[$name] = $callback;
    }

    /**
     * Mutate enum type.
     *
     * @param  string  $name
     * @param  \Closure  $callback
     * @return void
     *
     * @throws \Exception
     */
    public function enum($name, $callback)
    {
        if (! isset($this->nodeMap[$name])) {
            throw new Exception('Unknown type "'.$name.'".');
        }

        if (! $this->nodeMap[$name] instanceof EnumTypeDefinitionNode) {
            throw new Exception('Type "'.$name.'" is not of type enum.');
        }

        $this->typeMutators[$name] = $callback;
    }

    /**
     * Mutate input object type.
     *
     * @param  string  $name
     * @param  \Closure  $callback
     * @return void
     *
     * @throws \Exception
     */
    public function input($name, $callback)
    {
        if (! isset($this->nodeMap[$name])) {
            throw new Exception('Unknown type "'.$name.'".');
        }

        if (! $this->nodeMap[$name] instanceof InputObjectTypeDefinitionNode) {
            throw new Exception('Type "'.$name.'" is not of type input object.');
        }

        $this->typeMutators[$name] = $callback;
    }

    /**
     * Find directive.
     *
     * @param  string  $name
     * @return bool
     */
    protected function hasDirective($name)
    {
        foreach ($this->directiveDefs as $directive) {
            if ('@'.$directive->name->value === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply directive mutator.
     *
     * @param  \GraphQL\Type\Definition\Directive  $directive
     * @return void
     */
    protected function applyDirectiveMutator($directive)
    {
        $name = '@'.$directive->name;

        if (isset($this->directiveMutators[$name])) {
            $mutate = $this->directiveMutators[$name];

            $mutate($directive);
        }
    }

    /**
     * Apply type mutator.
     *
     * @param  \GraphQL\Type\Definition\NamedType  $type
     * @return void
     */
    protected function applyTypeMutator(NamedType $type)
    {
        if (isset($this->typeMutators[$type->name()])) {
            $mutate = $this->typeMutators[$type->name()];

            $mutate($type);
        }
    }
}
