<?php

use GraphQL\Executor\ExecutionResult;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use ProAI\Transporter\Normalizer;

beforeEach(function () {
    // Reset static properties
    Normalizer::$refKey = '__ref';
    Normalizer::$identifier = 'id';
});

it('has correct default static properties', function () {
    expect(Normalizer::$refKey)->toBe('__ref');
    expect(Normalizer::$identifier)->toBe('id');
});

it('prepares AST by adding __typename and id fields', function () {
    $schema = createNormalizerSchema();
    $ast = Parser::parse('query Test { user { name } }');

    $preparedAst = Normalizer::prepareAST($schema, $ast);

    $selections = $preparedAst->definitions[0]->selectionSet->selections[0]->selectionSet->selections;

    $fieldNames = [];
    foreach ($selections as $selection) {
        $fieldNames[] = $selection->name->value;
    }

    expect($fieldNames)->toContain('name');
    expect($fieldNames)->toContain('__typename');
    expect($fieldNames)->toContain('id');
});

it('does not add __typename to operation definition', function () {
    $schema = createNormalizerSchema();
    $ast = Parser::parse('query Test { user { name } }');

    $preparedAst = Normalizer::prepareAST($schema, $ast);

    $operationSelections = $preparedAst->definitions[0]->selectionSet->selections;
    $fieldNames = [];
    foreach ($operationSelections as $selection) {
        $fieldNames[] = $selection->name->value;
    }

    // Operation level should NOT have __typename added
    expect($fieldNames)->not->toContain('__typename');
});

it('transforms result to normalized format', function () {
    $schema = createNormalizerSchema();
    $ast = Parser::parse('query Test { user { id name } }');
    $preparedAst = Normalizer::prepareAST($schema, $ast);

    $executionResult = new ExecutionResult([
        'user' => ['id' => '1', 'name' => 'John', '__typename' => 'User'],
    ]);

    $result = Normalizer::transformResult($executionResult, $schema, $preparedAst, null);

    expect($result->data)->toHaveKey('roots');
    expect($result->data)->toHaveKey('entities');
    expect($result->data['entities']['User']['1'])->toHaveKey('name');
    expect($result->data['entities']['User']['1']['name'])->toBe('John');
});

it('returns null data result as-is', function () {
    $schema = createNormalizerSchema();
    $ast = Parser::parse('query Test { user { name } }');

    $executionResult = new ExecutionResult(null);

    $result = Normalizer::transformResult($executionResult, $schema, $ast, null);

    expect($result->data)->toBeNull();
});

it('creates references for entities with ids', function () {
    $schema = createNormalizerSchema();
    $ast = Parser::parse('query Test { user { id name } }');
    $preparedAst = Normalizer::prepareAST($schema, $ast);

    $executionResult = new ExecutionResult([
        'user' => ['id' => '1', 'name' => 'John', '__typename' => 'User'],
    ]);

    $result = Normalizer::transformResult($executionResult, $schema, $preparedAst, null);

    expect($result->data['roots']['user'])->toBe([Normalizer::$refKey => ['User', '1']]);
});

it('handles array results in normalization', function () {
    $schema = createNormalizerSchemaWithList();
    $ast = Parser::parse('query Test { users { id name } }');
    $preparedAst = Normalizer::prepareAST($schema, $ast);

    $executionResult = new ExecutionResult([
        'users' => [
            ['id' => '1', 'name' => 'John', '__typename' => 'User'],
            ['id' => '2', 'name' => 'Jane', '__typename' => 'User'],
        ],
    ]);

    $result = Normalizer::transformResult($executionResult, $schema, $preparedAst, null);

    expect($result->data['roots']['users'])->toHaveCount(2);
    expect($result->data['entities']['User'])->toHaveCount(2);
});

it('allows customizing the ref key', function () {
    Normalizer::$refKey = '$ref';

    $schema = createNormalizerSchema();
    $ast = Parser::parse('query Test { user { id name } }');
    $preparedAst = Normalizer::prepareAST($schema, $ast);

    $executionResult = new ExecutionResult([
        'user' => ['id' => '1', 'name' => 'John', '__typename' => 'User'],
    ]);

    $result = Normalizer::transformResult($executionResult, $schema, $preparedAst, null);

    expect($result->data['roots']['user'])->toHaveKey('$ref');
});

function createNormalizerSchema(): Schema
{
    $userType = new ObjectType([
        'name' => 'User',
        'fields' => [
            'id' => ['type' => Type::id()],
            'name' => ['type' => Type::string()],
        ],
    ]);

    return new Schema([
        'query' => new ObjectType([
            'name' => 'Query',
            'fields' => [
                'user' => [
                    'type' => $userType,
                    'resolve' => fn () => ['id' => '1', 'name' => 'John'],
                ],
            ],
        ]),
    ]);
}

function createNormalizerSchemaWithList(): Schema
{
    $userType = new ObjectType([
        'name' => 'User',
        'fields' => [
            'id' => ['type' => Type::id()],
            'name' => ['type' => Type::string()],
        ],
    ]);

    return new Schema([
        'query' => new ObjectType([
            'name' => 'Query',
            'fields' => [
                'users' => [
                    'type' => Type::listOf($userType),
                    'resolve' => fn () => [
                        ['id' => '1', 'name' => 'John'],
                        ['id' => '2', 'name' => 'Jane'],
                    ],
                ],
            ],
        ]),
    ]);
}
