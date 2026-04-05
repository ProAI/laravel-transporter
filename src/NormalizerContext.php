<?php

namespace ProAI\Transporter;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Schema;

class NormalizerContext
{
    /**
     * The schema instance.
     *
     * @var \GraphQL\Type\Schema
     */
    public Schema $schema;

    /**
     * The fragment nodes.
     *
     * @var \GraphQL\Language\AST\FragmentDefinitionNode[]
     */
    public array $fragments = [];

    /**
     * The operation nodes.
     *
     * @var \GraphQL\Language\AST\OperationDefinitionNode[]
     */
    public array $operations = [];

    /**
     * The variable values.
     *
     * @var array|null
     */
    public ?array $variableValues;

    /**
     * The normalized entities.
     *
     * @var array
     */
    public array $entities = [];

    /**
     * The normalized roots.
     *
     * @var array
     */
    public array $roots = [];

    /**
     * Create a new normalizer context instance.
     *
     * @param  \GraphQL\Type\Schema  $schema
     * @param  \GraphQL\Language\AST\DocumentNode  $ast
     * @param  array|null  $variableValues
     * @return void
     */
    public function __construct(Schema $schema, DocumentNode $ast, ?array $variableValues)
    {
        $this->schema = $schema;

        foreach ($ast->definitions as $node) {
            if ($node instanceof OperationDefinitionNode) {
                $this->operations[$node->name->value] = $node;
            }

            if ($node instanceof FragmentDefinitionNode) {
                $this->fragments[$node->name->value] = $node;
            }
        }

        $this->variableValues = $variableValues;
    }
}
