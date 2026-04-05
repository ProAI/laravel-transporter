<?php

namespace ProAI\Transporter\Type\Visitors;

use Exception;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use ProAI\Transporter\ArgumentBag;
use ProAI\Transporter\Resolvers\ConnectionResolver;
use ProAI\Transporter\Type\Definition\ObjectType;
use ProAI\Transporter\Type\FieldResolverAdapter;
use ReflectionProperty;

class ConnectionVisitor
{
    /**
     * The schemas that should be merged.
     *
     * @var \ProAI\Transporter\Type\Definition\ObjectType[]
     */
    protected array $cache = [];

    /**
     * The schemas that should be merged.
     *
     * @var \ProAI\Transporter\Type\Definition\ObjectType
     */
    protected ObjectType $pageInfoType;

    /**
     * Visit field definition.
     *
     * @param  \GraphQL\Type\Definition\FieldDefinition  $field
     * @param  \ProAI\Transporter\ArgumentBag  $args
     * @return void
     */
    public function visitFieldDefinition(FieldDefinition $field, ArgumentBag $args): void
    {
        $type = $this->unwrapType($field->getType());

        // Add field connection args
        $args = array_merge($field->config['args'] ?? null, $this->getConnectionArguments());
        $field->config['args'] = $args;
        $field->args = Argument::listFromConfig($args);

        // Overwrite field return type
        $connectionType = $this->getConnectionType($type);
        $reflection = new ReflectionProperty(FieldDefinition::class, 'type');
        $reflection->setAccessible(true);
        $field->config['type'] = $connectionType;
        $reflection->setValue($field, $connectionType);

        // Overwrite field resolver
        FieldResolverAdapter::forField($field, ConnectionResolver::class);
    }

    /**
     * Unwrap output type.
     *
     * @param  \GraphQL\Type\Definition\OutputType  $type
     * @return \GraphQL\Type\Definition\NamedType
     */
    protected function unwrapType(mixed $type): NamedType
    {
        if ($type instanceof NonNull) {
            $type = $type->getWrappedType();
        }

        if (! $type instanceof ListOfType) {
            throw new Exception('Type must be a list type.');
        }

        return $type->getInnermostType();
    }

    protected function getConnectionArguments(): array
    {
        return [
            'first' => [
                'type' => Type::int(),
            ],
            'last' => [
                'type' => Type::int(),
            ],
            'after' => [
                'type' => Type::string(),
            ],
            'before' => [
                'type' => Type::string(),
            ],
        ];
    }

    protected function getPageInfoType(): ObjectType
    {
        if (isset($this->pageInfoType)) {
            return $this->pageInfoType;
        }

        $pageInfoType = new ObjectType([
            'name' => 'PageInfo',
            'fields' => [
                'hasPreviousPage' => [
                    'type' => Type::nonNull(Type::boolean()),
                ],
                'hasNextPage' => [
                    'type' => Type::nonNull(Type::boolean()),
                ],
                'startCursor' => [
                    'type' => Type::string(),
                ],
                'endCursor' => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        return $this->pageInfoType = $pageInfoType;
    }

    protected function getConnectionType(NamedType $type): ObjectType
    {
        $name = $type->name();

        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $edgeType = new ObjectType([
            'name' => $name.'Edge',
            'fields' => [
                'node' => [
                    'type' => Type::nonNull($type),
                ],
                'cursor' => [
                    'type' => Type::nonNull(Type::string()),
                ],
            ],
        ]);

        $connectionType = new ObjectType([
            'name' => $name.'Connection',
            'fields' => [
                'edges' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($edgeType))),
                ],
                'nodes' => [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($type))),
                ],
                'pageInfo' => [
                    'type' => Type::nonNull($this->getPageInfoType()),
                ],
            ],
        ]);

        return $this->cache[$name] = $connectionType;
    }
}
