<?php

namespace ProAI\Transporter\Schema;

use Exception;
use GraphQL\Error\Error;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\EnumType as BaseEnumType;
use GraphQL\Type\Definition\InputObjectType as BaseInputObjectType;
use GraphQL\Type\Definition\InterfaceType as BaseInterfaceType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType as BaseObjectType;
use GraphQL\Type\Definition\ScalarType as BaseScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType as BaseUnionType;
use GraphQL\Utils\ASTDefinitionBuilder;
use ProAI\Transporter\Type\Definition\Directive;
use ProAI\Transporter\Type\Definition\EnumType;
use ProAI\Transporter\Type\Definition\InputObjectType;
use ProAI\Transporter\Type\Definition\InterfaceType;
use ProAI\Transporter\Type\Definition\ObjectType;
use ProAI\Transporter\Type\Definition\ScalarType;
use ProAI\Transporter\Type\Definition\UnionType;

class DefinitionBuilder
{
    /**
     * The ast definition builder instance.
     *
     * @var \GraphQL\Utils\ASTDefinitionBuilder
     */
    protected ASTDefinitionBuilder $builder;

    /**
     * The type instances cache.
     *
     * @var array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType>
     */
    protected array $cache = [];

    /**
     * Create a new ast definition builder instance.
     *
     * @param  array<string, \GraphQL\Language\AST\Node&\GraphQL\Language\AST\TypeDefinitionNode>  $typeDefinitionsMap
     * @param  (callable(string, Node|null): (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType))|null  $resolveType
     * @return void
     */
    public function __construct(array $typeDefinitionsMap, ?callable $resolveType = null)
    {
        // Build AST definition builder, but use alternative type definition
        // map keys, so that we can build customized types. Prefixing the types
        // with 'native.' is collision safe, because a dot is not allowed for
        // GraphQL type names.
        $nativeTypeDefinitionsMap = [];

        foreach ($typeDefinitionsMap as $name => $def) {
            $nativeTypeDefinitionsMap['native.'.$name] = $def;
        }

        $this->builder = new ASTDefinitionBuilder(
            $nativeTypeDefinitionsMap,
            [],
            function (string $typeName, ?Node $typeNode) use ($typeDefinitionsMap, $resolveType): Type&NamedType {
                if (isset($typeDefinitionsMap[$typeName])) {
                    return $this->buildType($typeName);
                }

                if ($resolveType !== null) {
                    return $resolveType($typeName, $typeNode);
                }

                throw new Error('Type "'.$typeName.'" not found in document.');
            }
        );
    }

    /**
     * Build type.
     *
     * @param  \GraphQL\Language\AST\NamedTypeNode|\GraphQL\Language\AST\TypeDefinitionNode|string  $ref
     * @return \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType
     */
    public function buildType(mixed $ref): Type&NamedType
    {
        $name = is_string($ref) ? $ref : $ref->name->value;

        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        // Use 'native.' type name, because we do not want to cache the
        // untransformed type under the real name.
        $this->cache[$name] = $this->transformType(
            $this->builder->buildType('native.'.$name)
        );

        return $this->cache[$name];
    }

    /**
     * Transform type to transporter type.
     *
     * @param  \GraphQL\Type\Definition\NamedType  $type
     * @return \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType
     */
    protected function transformType(NamedType $type): Type&NamedType
    {
        switch (true) {
            case $type instanceof BaseObjectType:
                return new ObjectType($type->config);
            case $type instanceof BaseInterfaceType:
                return new InterfaceType($type->config);
            case $type instanceof BaseEnumType:
                return new EnumType($type->config);
            case $type instanceof BaseUnionType:
                return new UnionType($type->config);
            case $type instanceof BaseScalarType:
                return new ScalarType($type->config);
            case $type instanceof BaseInputObjectType:
                return new InputObjectType($type->config);
            default:
                throw new Exception('Type not supported.');
        }
    }

    /**
     * Build directive.
     *
     * @param  \GraphQL\Language\AST\DirectiveDefinitionNode  $directiveNode
     * @return \ProAI\Transporter\Type\Definition\Directive
     */
    public function buildDirective(DirectiveDefinitionNode $directiveNode): Directive
    {
        $directive = $this->builder->buildDirective($directiveNode);

        $config = $directive->config;

        return new Directive($config);
    }
}
