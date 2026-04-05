<?php

namespace ProAI\Transporter\Schema;

use Exception;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Utils\TypeComparators;
use ProAI\Transporter\Type\Definition\ObjectType;
use ProAI\Transporter\Type\Types;

class Merger
{
    /**
     * The schemas that should be merged.
     *
     * @var \GraphQL\Type\Schema[]
     */
    protected array $schemas;

    /**
     * The factories of schemas that should be merged.
     *
     * @var \ProAI\Transporter\Schema\Factory[]
     */
    protected array $factories;

    /**
     * Create a new schema merger instance.
     *
     * @param  \GraphQL\Type\Schema[]  $schemas
     * @param  \ProAI\Transporter\Schema\Factory[]  $factories
     * @return void
     */
    public function __construct(array $schemas, array $factories)
    {
        $this->schemas = $schemas;

        $this->factories = $factories;
    }

    /**
     * Build merged schema.
     *
     * @return \GraphQL\Type\Schema
     */
    public function merge(): Schema
    {
        $preBuiltTypeMap = $this->getPreBuiltTypeMap();

        // create AST definition builder with merged type defs
        $defBuilder = new DefinitionBuilder(
            $this->getNodeMap(),
            function (string $typeName) use ($preBuiltTypeMap) {
                if (isset($preBuiltTypeMap[$typeName])) {
                    return $preBuiltTypeMap[$typeName];
                }

                throw new Exception('Type "'.$typeName.'" not found in document.');
            }
        );

        // build and merge all directives and set them in definition builder
        $directivesHandler = new DirectivesHandler(
            $this->buildDirectives(
                $defBuilder,
                $this->getPreBuiltDirectives()
            )
        );

        $queryType = $this->buildOperationType('query', $defBuilder, $directivesHandler);

        $schema = new Schema([
            'query' => $queryType,
            'mutation' => $this->buildOperationType('mutation', $defBuilder, $directivesHandler),
            'subscription' => $this->buildOperationType('subscription', $defBuilder, $directivesHandler),
            'types' => $this->buildTypes($defBuilder, $directivesHandler, $preBuiltTypeMap),
            'directives' => $directivesHandler->getDirectives(),
        ]);

        $schema->assertValid();

        return $schema;
    }

    /**
     * Get merged node maps of factories.
     *
     * @return array<string, \GraphQL\Language\AST\Node&\GraphQL\Language\AST\TypeDefinitionNode>
     *
     * @throws \Exception
     */
    protected function getNodeMap(): array
    {
        $resultNodeMap = [];

        foreach ($this->factories as $factory) {
            $nodeMap = $factory->getNodeMapWithoutOperationDefs();

            foreach ($nodeMap as $def) {
                $name = $def->getName()->value;

                if (isset($resultNodeMap[$name]) && $resultNodeMap[$name] != $def) {
                    throw new Exception('Duplicate definition for type "'.$name.'".');
                }

                $resultNodeMap[$name] = $def;
            }
        }

        return $resultNodeMap;
    }

    /**
     * Get types of already built schemas.
     *
     * @return array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType>
     */
    protected function getPreBuiltTypeMap(): array
    {
        $types = [];

        foreach ($this->schemas as $schema) {
            $types = $this->mergeTypes(
                $types,
                $this->getTypeMapWithoutOperationTypes($schema)
            );
        }

        $typeMap = [];

        foreach ($types as $type) {
            $typeMap[$type->name()] = $type;
        }

        return $typeMap;
    }

    /**
     * Get type map of schema without operation types.
     *
     * @param  \GraphQL\Type\Schema  $schema
     * @return array<string, \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType>
     */
    protected function getTypeMapWithoutOperationTypes(Schema $schema): array
    {
        $typeMap = $schema->getTypeMap();

        foreach (['query', 'mutation', 'subscription'] as $operation) {
            if ($type = $schema->{'get'.ucfirst($operation).'Type'}()) {
                unset($typeMap[$type->name()]);
            }
        }

        return $typeMap;
    }

    /**
     * Get directives of already built schemas.
     *
     * @return \GraphQL\Type\Definition\Directive[]
     */
    protected function getPreBuiltDirectives(): array
    {
        $directives = [];

        foreach ($this->schemas as $schema) {
            $directives = array_merge($directives, $schema->getDirectives());
        }

        return $directives;
    }

    /**
     * Build and merge all directives.
     *
     * @param  \ProAI\Transporter\Schema\DefinitionBuilder  $defBuilder
     * @param  \GraphQL\Type\Definition\Directive[]  $directives
     * @return \GraphQL\Type\Definition\Directive[]
     */
    protected function buildDirectives(DefinitionBuilder $defBuilder, array $directives = []): array
    {
        foreach ($this->factories as $factory) {
            $directives = array_merge(
                $directives,
                $factory->buildDirectives($defBuilder)
            );
        }

        return $directives;
    }

    /**
     * Build and merge all types (except operation types).
     *
     * @param  \ProAI\Transporter\Schema\DefinitionBuilder  $defBuilder
     * @param  \ProAI\Transporter\Schema\DirectivesHandler  $directivesHandler
     * @param  (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType)[]  $types
     * @return (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType)[]
     */
    protected function buildTypes(DefinitionBuilder $defBuilder,
        DirectivesHandler $directivesHandler,
        array $types = []): array
    {
        foreach ($this->factories as $factory) {
            $types = $this->mergeTypes(
                $types,
                $factory->buildTypes($defBuilder, $directivesHandler)
            );
        }

        return $types;
    }

    /**
     * Merge type arrays.
     *
     * @param  (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType)[]  $leftTypes
     * @param  (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType)[]  $rightTypes
     * @return (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType)[]
     *
     * @throws \Exception
     */
    protected function mergeTypes(array $leftTypes, array $rightTypes): array
    {
        $returnTypes = [];
        $leftTypeMap = [];

        foreach ($leftTypes as $type) {
            $returnTypes[] = $type;
            $leftTypeMap[$type->name()] = $type;
        }

        foreach ($rightTypes as $type) {
            if (isset($leftTypeMap[$type->name()]) && ! TypeComparators::isEqualType($leftTypeMap[$type->name()], $type)) {
                throw new Exception('Type merge conflict: Two different types of name "'.$type->name().'".');
            }

            $returnTypes[] = $type;
        }

        return $returnTypes;
    }

    /**
     * Build operation type.
     *
     * @param  string  $operation
     * @param  \ProAI\Transporter\Schema\DefinitionBuilder  $defBuilder
     * @param  \ProAI\Transporter\Schema\DirectivesHandler  $directivesHandler
     * @return \ProAI\Transporter\Type\Definition\ObjectType|null
     */
    protected function buildOperationType(string $operation,
        DefinitionBuilder $defBuilder,
        DirectivesHandler $directivesHandler): ?ObjectType
    {
        $types = [];

        // collect operation types from schemas
        foreach ($this->schemas as $schema) {
            if ($type = $schema->{'get'.ucfirst($operation).'Type'}()) {
                $types[] = $type;
            }
        }

        // collect operation types from factories
        foreach ($this->factories as $factory) {
            if ($type = $factory->buildOperationType($operation, $defBuilder, $directivesHandler)) {
                $types[] = $type;
            }
        }

        if (count($types) === 0) {
            return null;
        }

        return $this->mergeObjectTypes([
            'name' => ucfirst($operation),
            'description' => 'The '.$operation.' operation type of the schema.',
        ], $types);
    }

    /**
     * Merge object types and return new object type.
     *
     * @param  array  $config
     * @param  \GraphQL\Type\Definition\ObjectType[]  $types
     * @return \ProAI\Transporter\Type\Definition\ObjectType
     */
    protected function mergeObjectTypes(array $config, array $types): ObjectType
    {
        $mergedFields = [];
        $mergedInterfaces = [];

        foreach ($types as $type) {
            $fields = $type->config['fields'];

            if (is_callable($fields)) {
                $fields = $fields();
            }

            $mergedFields = array_merge($mergedFields, $fields);

            if (isset($type->config['interfaces'])) {
                $interfaces = $type->config['interfaces'];

                if (is_callable($interfaces)) {
                    $interfaces = $interfaces();
                }

                if ($interfaces) {
                    $mergedInterfaces = array_merge($mergedInterfaces, $interfaces);
                }
            }
        }

        return new ObjectType(array_merge($config, [
            'fields' => $mergedFields,
            'interfaces' => $mergedInterfaces,
        ]));
    }
}
