<?php

namespace ProAI\Transporter\Schema\Concerns;

use Closure;
use GraphQL\Type\Definition\CustomScalarType as BaseCustomScalarType;
use GraphQL\Type\Definition\Directive as BaseDirective;
use GraphQL\Type\Definition\EnumType as BaseEnumType;
use GraphQL\Type\Definition\HasFieldsType;
use GraphQL\Type\Definition\InputObjectType as BaseInputObjectType;
use GraphQL\Type\Definition\InterfaceType as BaseInterfaceType;
use GraphQL\Type\Definition\ObjectType as BaseObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType as BaseUnionType;
use GraphQL\Type\Schema;
use ReflectionProperty;

trait SerializesSchema
{
    /**
     * Prepare schema for serialization.
     *
     * @param  \GraphQL\Type\Schema  $schema
     * @return void
     */
    protected function prepareForSerialization(Schema $schema)
    {
        // Here we do some optimizations to minimize the filesize of the
        // serialized schema file for performance reasons.

        $schema->getConfig()->astNode = null;

        foreach ($schema->getDirectives() as $directive) {
            $this->removeASTFromDirective($directive);
        }

        foreach ($schema->getTypeMap() as $type) {
            $this->resolveThunks($type);

            $this->wrapFieldResolvers($type);

            $this->wrapScalarCoercion($type);

            $this->removeASTFromType($type);

            $this->resetLazyLoadedFields($type);
        }
    }

    /**
     * Remove AST from directive.
     *
     * @param  \GraphQL\Type\Definition\Directive  $directive
     * @return void
     */
    protected function removeASTFromDirective(BaseDirective $directive)
    {
        // We want to remove the ast nodes from the directive, because they are
        // not needed after serialization for validation and execution. Without
        // these nodes it is more performant to unserialize the schema.

        $directive->astNode = null;
        unset($directive->config['astNode']);
    }

    /**
     * Resolve thunks of type.
     *
     * @param  \GraphQL\Type\Definition\Type  $type
     * @return void
     */
    protected function resolveThunks(Type $type)
    {
        if ($type instanceof BaseUnionType) {
            $types = $type->config['types'] ?? null;

            if (is_callable($types)) {
                $type->config['types'] = $types();
            }
        }

        if ($type instanceof BaseObjectType || $type instanceof BaseInputObjectType || $type instanceof BaseInterfaceType) {
            $fields = $type->config['fields'] ?? null;

            if (is_callable($fields)) {
                $type->config['fields'] = $fields();
            }
        }

        if ($type instanceof BaseObjectType || $type instanceof BaseInterfaceType) {
            $interfaces = $type->config['interfaces'] ?? null;

            if (is_callable($interfaces)) {
                $type->config['interfaces'] = $interfaces();
            }
        }
    }

    /**
     * Wrap field resolvers in serializable closure where needed.
     *
     * @param  \GraphQL\Type\Definition\Type  $type
     * @return void
     */
    protected function wrapFieldResolvers(Type $type)
    {
        if (! $type instanceof BaseObjectType) {
            return;
        }

        foreach ($type->getFields() as $field) {
            if (! $field->resolveFn instanceof Closure) {
                continue;
            }

            $field->resolveFn = $field->config['resolve']
                              = $type->config['fields'][$field->name]['resolve']
                              = $field->resolveFn;
        }
    }

    /**
     * Wrap scalar coercion callables.
     *
     * @param  \GraphQL\Type\Definition\Type  $type
     * @return void
     */
    protected function wrapScalarCoercion(Type $type)
    {
        if (! $type instanceof BaseCustomScalarType) {
            return;
        }

        foreach (['serialize', 'parseValue', 'parseLiteral'] as $method) {
            if (! isset($type->config[$method])) {
                continue;
            }

            $fn = $type->config[$method];

            if (! $fn instanceof Closure) {
                continue;
            }

            $type->config[$method] = $fn;
        }
    }

    /**
     * Remove AST from type.
     *
     * @param  \GraphQL\Type\Definition\Type  $type
     * @return void
     */
    protected function removeASTFromType(Type $type)
    {
        // We want to remove the ast nodes from the type, because they are not
        // needed after serialization for validation and execution. Without
        // these nodes it is more performant to unserialize the schema.

        unset($type->astNode);
        unset($type->extensionASTNodes);

        if ($type instanceof BaseObjectType || $type instanceof BaseInterfaceType ||
            $type instanceof BaseEnumType || $type instanceof BaseUnionType ||
            $type instanceof BaseCustomScalarType || $type instanceof BaseInputObjectType) {
            unset($type->config['astNode']);
            unset($type->config['extensionASTNodes']);
        }

        if ($type instanceof BaseObjectType || $type instanceof BaseInterfaceType) {
            foreach ($type->config['fields'] as $fieldKey => $field) {
                unset($type->config['fields'][$fieldKey]['astNode']);

                if (! isset($field['args'])) {
                    continue;
                }

                foreach ($field['args'] as $argKey => $arg) {
                    unset($type->config['fields'][$fieldKey]['args'][$argKey]['astNode']);
                }
            }
        }

        if ($type instanceof BaseEnumType) {
            foreach ($type->config['values'] as $key => $value) {
                unset($type->config['values'][$key]['astNode']);
            }
        }

        if ($type instanceof BaseInputObjectType) {
            foreach ($type->config['fields'] as $key => $field) {
                unset($type->config['fields'][$key]['astNode']);
            }
        }
    }

    /**
     * Reset lazily loaded properties.
     *
     * @param  \GraphQL\Type\Definition\Type  $type
     * @return void
     */
    protected function resetLazyLoadedFields(Type $type)
    {
        // We want to unset lazily loaded fields to shrink the cached schema
        // file for a better performance. We only need to do this for the base
        // types, because the laravel transporter types support serialization
        // out of the box.

        if (get_class($type) === BaseObjectType::class) {
            $this->resetFields(BaseObjectType::class, $type);
        }

        if (get_class($type) === BaseInterfaceType::class) {
            $this->resetFields(BaseInterfaceType::class, $type);
        }
    }

    /**
     * Reset lazily loaded property fields of a type.
     *
     * @param  string  $class
     * @param  \GraphQL\Type\Definition\HasFieldsType  $type
     * @return void
     */
    protected function resetFields($class, HasFieldsType $type)
    {
        $store = new LazyFieldStore($type);

        $reflection = new ReflectionProperty($class, 'fields');
        $reflection->setAccessible(true);

        $values = $reflection->getValue($type);

        foreach ($values as $key => $value) {
            $values[$key] = new LazyFieldDefinition($key, $store);
        }

        $reflection->setValue($type, $values);
    }
}
