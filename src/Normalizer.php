<?php

namespace ProAI\Transporter;

use GraphQL\Executor\ExecutionResult;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\HasFieldsType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\TypeComparators;
use GraphQL\Utils\TypeInfo;
use Illuminate\Support\Arr;
use stdClass;

class Normalizer
{
    /**
     * The key of a reference.
     *
     * @var string
     */
    public static string $refKey = '__ref';

    /**
     * The identifier field of an object type.
     *
     * @var string
     */
    public static string $identifier = 'id';

    /**
     * Execute query and return normalized result.
     *
     * @param  \GraphQL\Type\Schema  $schema
     * @param  string|\GraphQL\Language\AST\DocumentNode  $source
     * @param  mixed  $rootValue
     * @param  mixed  $contextValue
     * @param  array|null  $variableValues
     * @param  string|null  $operationName
     * @param  callable|null  $fieldResolver
     * @param  \GraphQL\Validator\Rules\ValidationRule[]|null  $validationRules
     * @return ExecutionResult
     */
    public static function executeQuery(Schema $schema,
        string|DocumentNode $source,
        mixed $rootValue = null,
        mixed $contextValue = null,
        ?array $variableValues = null,
        ?string $operationName = null,
        ?callable $fieldResolver = null,
        ?array $validationRules = null): ExecutionResult
    {
        if ($source instanceof DocumentNode) {
            $documentNode = $source;
        } else {
            $documentNode = Parser::parse(new Source($source, 'GraphQL'));
        }

        $documentNode = static::prepareAST($schema, $documentNode);

        $result = GraphQL::executeQuery(
            $schema,
            $documentNode,
            $rootValue,
            $contextValue,
            $variableValues,
            $operationName,
            $fieldResolver,
            $validationRules
        );

        return static::transformResult(
            $result,
            $schema,
            $documentNode,
            $variableValues
        );
    }

    /**
     * Prepare AST for a normalized result, ergo adding id and __typename fields.
     *
     * @param  \GraphQL\Type\Schema  $schema
     * @param  \GraphQL\Language\AST\DocumentNode  $ast
     * @return \GraphQL\Language\AST\DocumentNode
     */
    public static function prepareAST(Schema $schema, DocumentNode $ast): DocumentNode
    {
        $typeInfo = new TypeInfo($schema);

        return Visitor::visit(
            $ast,
            Visitor::visitWithTypeInfo($typeInfo, [
                NodeKind::SELECTION_SET => static function (Node $node, string|int|null $key = null, mixed $parent = null) use ($typeInfo) {
                    if (! $node instanceof SelectionSetNode) {
                        return;
                    }

                    // Do not add "__typename" and "id" to operation definition.
                    if ($parent->kind === NodeKind::OPERATION_DEFINITION) {
                        return;
                    }

                    $parentType = $typeInfo->getParentType();

                    // If parent type does not exist because of an invalid
                    // schema, return.
                    if (is_null($parentType)) {
                        return;
                    }

                    // Union types do not have an identifier field. We add the
                    // identifier to the fragments.
                    if ($parentType instanceof UnionType) {
                        return;
                    }

                    // Always add __typename; also add identifier if type has identifier field.
                    if ($parentType instanceof HasFieldsType && $parentType->hasField(static::$identifier)) {
                        $node->selections = $node->selections->merge([
                            new FieldNode([
                                'name' => new NameNode(['value' => '__typename']),
                            ]),
                            new FieldNode([
                                'name' => new NameNode(['value' => static::$identifier]),
                            ]),
                        ]);
                    } else {
                        $node->selections = $node->selections->merge([
                            new FieldNode([
                                'name' => new NameNode(['value' => '__typename']),
                            ]),
                        ]);
                    }

                    return $node;
                },
            ])
        );
    }

    /**
     * Transform result to a normalized result.
     *
     * @param  \GraphQL\Executor\ExecutionResult  $result
     * @param  \GraphQL\Type\Schema  $schema
     * @param  \GraphQL\Language\AST\DocumentNode  $ast
     * @param  array|null  $variableValues
     * @return \GraphQL\Executor\ExecutionResult
     */
    public static function transformResult(ExecutionResult $result,
        Schema $schema,
        DocumentNode $ast,
        ?array $variableValues): ExecutionResult
    {
        if ($result->data === null) {
            return $result;
        }

        $context = new NormalizerContext($schema, $ast, $variableValues);

        foreach ($context->operations as $operation) {
            foreach ($operation->selectionSet->selections as $field) {
                if (! $field instanceof FieldNode) {
                    continue;
                }

                $name = $field->name->value;

                if ($operation->operation === 'mutation') {
                    $key = $name;
                } else {
                    $key = static::getKeyWithArguments(
                        $name,
                        $field,
                        $variableValues
                    );
                }

                $value = $result->data[$name];

                if ($field->selectionSet) {
                    $context->roots[$key] = static::normalizeResultNode(
                        $value, $field->selectionSet, $context
                    );
                } else {
                    $context->roots[$key] = $value;
                }
            }
        }

        $result->data = [
            'roots' => $context->roots,
            'entities' => $context->entities,
        ];

        return $result;
    }

    /**
     * Normalize result node.
     *
     * @param  array|stdClass|null  $result
     * @param  \GraphQL\Language\AST\SelectionSetNode  $node
     * @param  \ProAI\Transporter\NormalizerContext  $context
     * @return array|stdClass|null
     */
    protected static function normalizeResultNode(null|array|stdClass $result, SelectionSetNode $node, NormalizerContext $context): null|array|stdClass
    {
        if ($result === null) {
            return null;
        }

        // For empty results there might be an empty stdClass object.
        if ($result instanceof stdClass) {
            return $result;
        }

        if (! Arr::isAssoc($result)) {
            $values = Arr::map($result, function (mixed $item) use ($node, $context) {
                $value = static::normalizeResultNode($item, $node, $context);

                return $value;
            });

            return $values;
        }

        $id = Arr::pull($result, static::$identifier);
        $typename = Arr::get($result, '__typename');
        $nodes = static::gatherFieldNodes($typename, $node, $context);

        // If there is no identifier, just return the non normalized result.
        if (is_null($id)) {
            return static::createEntity($result, $nodes, $context);
        }

        Arr::pull($result, '__typename');

        if (! isset($context->entities[$typename])) {
            $context->entities[$typename] = [];
        }

        $entity = static::createEntity($result, $nodes, $context);

        $context->entities[$typename][$id] = static::mergeEntity(
            $context->entities[$typename][$id] ?? null,
            $entity
        );

        return [static::$refKey => [$typename, $id]];
    }

    /**
     * Gather field nodes.
     *
     * @param  string  $typename
     * @param  \GraphQL\Language\AST\SelectionSetNode  $node
     * @param  \ProAI\Transporter\NormalizerContext  $context
     * @return \GraphQL\Language\AST\SelectionSetNode[]
     */
    protected static function gatherFieldNodes(string $typename, SelectionSetNode $node, NormalizerContext $context): array
    {
        $result = [];

        $fields = [];

        foreach ($node->selections as $field) {
            if ($field instanceof FragmentSpreadNode) {
                $name = $field->name->value;
                $typeCondition = $context->fragments[$name]->typeCondition;

                if (static::isSubType($typename, $typeCondition, $context)) {
                    $result = array_merge(
                        $result, static::gatherFieldNodes(
                            $typename,
                            $context->fragments[$name]->selectionSet,
                            $context
                        )
                    );
                }
            } elseif ($field instanceof InlineFragmentNode) {
                $typeCondition = $field->typeCondition;

                if (static::isSubType($typename, $typeCondition, $context)) {
                    $result = array_merge(
                        $result, static::gatherFieldNodes(
                            $typename,
                            $field->selectionSet,
                            $context
                        )
                    );
                }
            } elseif ($field instanceof FieldNode) {
                $name = $field->name->value;

                $fields[$name] = $field;
            }
        }

        $result[] = new SelectionSetNode([
            'selections' => new NodeList(array_values($fields)),
        ]);

        return $result;
    }

    /**
     * Determine whether the given typename is subtype of given named type node.
     *
     * @param  string  $typename
     * @param  \GraphQL\Language\AST\NamedTypeNode  $node
     * @param  \ProAI\Transporter\NormalizerContext  $context
     * @return bool
     */
    protected static function isSubType(string $typename, NamedTypeNode $node, NormalizerContext $context): bool
    {
        $superTypename = $node->name->value;

        if ($typename === $superTypename) {
            return true;
        }

        return TypeComparators::isTypeSubTypeOf(
            $context->schema,
            $context->schema->getType($typename),
            $context->schema->getType($superTypename)
        );
    }

    /**
     * Create entity.
     *
     * @param  array  $result
     * @param  \GraphQL\Language\AST\SelectionSetNode[]  $nodes
     * @param  \ProAI\Transporter\NormalizerContext  $context
     * @return array|stdClass
     */
    protected static function createEntity(array $result, array $nodes, NormalizerContext $context): array|stdClass
    {
        $entity = [];

        foreach ($nodes as $node) {
            foreach ($node->selections as $field) {
                if (! $field instanceof FieldNode) {
                    continue;
                }

                $name = $field->name->value;

                if (! array_key_exists($name, $result)) {
                    continue;
                }

                $value = $result[$name];

                $key = static::getKeyWithArguments(
                    $name,
                    $field,
                    $context->variableValues
                );

                if ($field->selectionSet) {
                    $entity[$key] = static::normalizeResultNode(
                        $value, $field->selectionSet, $context
                    );
                } else {
                    $entity[$key] = $value;
                }
            }
        }

        if (empty($entity)) {
            return new stdClass;
        }

        return $entity;
    }

    /**
     * Merge entity with an existing entity.
     *
     * @param  array|stdClass|null  $existing
     * @param  array|stdClass  $entity
     * @return array|stdClass
     */
    protected static function mergeEntity(null|array|stdClass $existing, array|stdClass $entity): array|stdClass
    {
        if (! is_array($entity)) {
            return $existing ?? $entity;
        }

        if (! is_array($existing)) {
            return $entity;
        }

        return array_merge($entity, $existing);
    }

    /**
     * Get the cache key for a field with its arguments.
     *
     * @param  string  $key
     * @param  \GraphQL\Language\AST\FieldNode  $field
     * @param  array|null  $variableValues
     * @return string
     */
    protected static function getKeyWithArguments(string $key, FieldNode $field, ?array $variableValues): string
    {
        $args = [];

        foreach ($field->arguments as $argument) {
            $value = AST::valueFromASTUntyped(
                $argument->value,
                $variableValues
            );

            $args[$argument->name->value] = $value;
        }

        $args = static::sortArguments($args);

        if (count($args) === 0) {
            return $key;
        }

        return $key.'('.json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).')';
    }

    /**
     * Sort (and filter) arguments recursively.
     *
     * @param  array  $arguments
     * @return array
     */
    protected static function sortArguments(array $arguments): array
    {
        $returnArgs = [];

        foreach ($arguments as $key => $value) {
            if ($value === null) {
                continue;
            }

            $returnArgs[$key] = is_array($value)
                ? static::sortArguments($value)
                : $value;
        }

        ksort($returnArgs);

        return $returnArgs;
    }
}
