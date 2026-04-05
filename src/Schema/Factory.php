<?php

namespace ProAI\Transporter\Schema;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

class Factory
{
    use Concerns\HasMutators,
        Concerns\SynchronizesConfig;

    /**
     * The schema definition.
     *
     * @var \GraphQL\Language\AST\SchemaDefinitionNode
     */
    protected ?SchemaDefinitionNode $schemaDef = null;

    /**
     * The type definitions mapped by name.
     *
     * @var array<string, \GraphQL\Language\AST\Node&\GraphQL\Language\AST\TypeDefinitionNode>
     */
    protected array $nodeMap = [];

    /**
     * The directive definitions.
     *
     * @var \GraphQL\Language\AST\DirectiveDefinitionNode[]
     */
    protected array $directiveDefs = [];

    /**
     * The operation type definitions.
     *
     * @var array
     */
    protected array $operationTypeDefs;

    /**
     * Create a new schema factory instance.
     *
     * @param  \GraphQL\Language\AST\DocumentNode  $astNode
     * @return void
     */
    public function __construct(DocumentNode $astNode)
    {
        // From JavaScript reference implementation
        // https://github.com/graphql/graphql-js/blob/master/src/utilities/buildASTSchema.js

        foreach ($astNode->definitions as $def) {
            if ($def instanceof SchemaDefinitionNode) {
                $this->schemaDef = $def;
            } elseif ($def instanceof TypeDefinitionNode) {
                $this->nodeMap[$def->getName()->value] = $def;
            } elseif ($def instanceof DirectiveDefinitionNode) {
                $this->directiveDefs[] = $def;
            }
        }

        $this->operationTypeDefs = $this->schemaDef
            ? $this->getOperationTypeDefs($this->schemaDef)
            : [
                'query' => $this->nodeMap['Query'] ?? null,
                'mutation' => $this->nodeMap['Mutation'] ?? null,
                'subscription' => $this->nodeMap['Subscription'] ?? null,
            ];
    }

    /**
     * Apply directive mutators.
     *
     * @param  \GraphQL\Language\AST\Node  $node
     * @return bool
     */
    protected function isTypeDefinitionNode(Node $node): bool
    {
        return $node->kind === NodeKind::SCALAR_TYPE_DEFINITION ||
            $node->kind === NodeKind::OBJECT_TYPE_DEFINITION ||
            $node->kind === NodeKind::INTERFACE_TYPE_DEFINITION ||
            $node->kind === NodeKind::ENUM_TYPE_DEFINITION ||
            $node->kind === NodeKind::UNION_TYPE_DEFINITION ||
            $node->kind === NodeKind::INPUT_OBJECT_TYPE_DEFINITION;
    }

    /**
     * Get type defs of operation types.
     *
     * @param  \GraphQL\Language\AST\SchemaDefinitionNode  $schemaNode
     * @return array
     */
    protected function getOperationTypeDefs(SchemaDefinitionNode $schemaNode): array
    {
        $opTypeDefs = [
            'query' => null,
            'mutation' => null,
            'subscription' => null,
        ];

        foreach ($schemaNode->operationTypes as $def) {
            $opTypeDefs[$def->operation] = $def->type;
        }

        return $opTypeDefs;
    }

    /**
     * Include build instructions from PHP file.
     *
     * @param  string  $path
     * @return void
     */
    public function includeFile(string $path): void
    {
        $schema = $this;

        require $path;
    }

    /**
     * Make the schema (equivalent to buildASTSchema function in JavaScript implementation).
     *
     * @return \GraphQL\Type\Schema
     */
    public function make(): Schema
    {
        $defBuilder = new DefinitionBuilder($this->nodeMap);

        $directivesHandler = new DirectivesHandler(
            $this->buildDirectives($defBuilder)
        );

        $schema = new Schema([
            'query' => $this->buildOperationType('query', $defBuilder, $directivesHandler),
            'mutation' => $this->buildOperationType('mutation', $defBuilder, $directivesHandler),
            'subscription' => $this->buildOperationType('subscription', $defBuilder, $directivesHandler),
            'types' => $this->buildTypes($defBuilder, $directivesHandler),
            'directives' => $directivesHandler->getDirectives(),
            'astNode' => $this->schemaDef,
        ]);

        $schema->assertValid();

        return $schema;
    }

    /**
     * Build all directives.
     *
     * @param  \ProAI\Transporter\Schema\DefinitionBuilder  $defBuilder
     * @return \ProAI\Transporter\Type\Definition\Directive[]
     */
    public function buildDirectives(DefinitionBuilder $defBuilder): array
    {
        $directives = [];

        foreach ($this->directiveDefs as $def) {
            $directive = $defBuilder->buildDirective($def);

            $this->applyDirectiveMutator($directive);

            $directives[] = $directive;
        }

        return $directives;
    }

    /**
     * Build all types (except operation types).
     *
     * @param  \ProAI\Transporter\Schema\DefinitionBuilder  $defBuilder
     * @param  \ProAI\Transporter\Schema\DirectivesHandler  $directivesHandler
     * @return (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType)[]
     */
    public function buildTypes(DefinitionBuilder $defBuilder,
        DirectivesHandler $directivesHandler): array
    {
        $types = [];
        $nodeMap = $this->getNodeMapWithoutOperationDefs();

        // Build all types
        foreach ($nodeMap as $def) {
            $types[] = $defBuilder->buildType($def);
        }

        // After the creation of all types we can apply the directives on and
        // the mutators of the type definitions.
        foreach ($types as $type) {
            $def = $nodeMap[$type->name()];

            $this->mutateType($def, $type, $directivesHandler);
        }

        return $types;
    }

    /**
     * Get node without definitions of operation types.
     *
     * @return array<string, \GraphQL\Language\AST\Node&\GraphQL\Language\AST\TypeDefinitionNode>
     */
    public function getNodeMapWithoutOperationDefs(): array
    {
        $nodeMap = $this->nodeMap;

        foreach (['query', 'mutation', 'subscription'] as $operation) {
            if ($def = $this->operationTypeDefs[$operation]) {
                unset($nodeMap[$def->name->value]);
            }
        }

        return $nodeMap;
    }

    /**
     * Build operation type.
     *
     * @param  string  $operation
     * @param  \ProAI\Transporter\Schema\DefinitionBuilder  $defBuilder
     * @param  \ProAI\Transporter\Schema\DirectivesHandler  $directivesHandler
     * @return \GraphQL\Type\Definition\Type
     */
    public function buildOperationType(string $operation,
        DefinitionBuilder $defBuilder,
        DirectivesHandler $directivesHandler): ?Type
    {
        $def = $this->operationTypeDefs[$operation];

        if (! $def) {
            return null;
        }

        $operationDefBuilder = new DefinitionBuilder(
            [$def->name->value => $def],
            function (string $typeName) use ($defBuilder) {
                return $defBuilder->buildType($typeName);
            }
        );

        $type = $operationDefBuilder->buildType($def);

        $this->mutateType($def, $type, $directivesHandler);

        return $type;
    }

    /**
     * Mutate type with mutators and directives.
     *
     * @param  \GraphQL\Language\AST\TypeDefinitionNode  $def
     * @param  \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType  $type
     * @param  \ProAI\Transporter\Schema\DirectivesHandler  $directivesHandler
     * @return void
     */
    protected function mutateType(TypeDefinitionNode $def,
        Type&NamedType $type,
        DirectivesHandler $directivesHandler): void
    {
        $directivesHandler->applyDirectives($def, $type);

        $this->applyTypeMutator($type);

        // Sync config after mutations.
        $this->syncConfig($type);
    }
}
