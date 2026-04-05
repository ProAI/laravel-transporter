<?php

use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use ProAI\Transporter\NormalizerContext;

it('parses operations from document node', function () {
    $schema = createTestSchema();

    $ast = Parser::parse('query TestQuery { hello }');

    $context = new NormalizerContext($schema, $ast, null);

    expect($context->operations)->toHaveCount(1);
    expect($context->operations['TestQuery'])->not->toBeNull();
});

it('parses fragments from document node', function () {
    $schema = createTestSchema();

    $ast = Parser::parse('
        query TestQuery { hello ...MyFragment }
        fragment MyFragment on Query { hello }
    ');

    $context = new NormalizerContext($schema, $ast, null);

    expect($context->fragments)->toHaveCount(1);
    expect($context->fragments['MyFragment'])->not->toBeNull();
});

it('stores schema reference', function () {
    $schema = createTestSchema();
    $ast = Parser::parse('query TestQuery { hello }');

    $context = new NormalizerContext($schema, $ast, null);

    expect($context->schema)->toBe($schema);
});

it('stores variable values', function () {
    $schema = createTestSchema();
    $ast = Parser::parse('query TestQuery { hello }');

    $variables = ['name' => 'John'];
    $context = new NormalizerContext($schema, $ast, $variables);

    expect($context->variableValues)->toBe($variables);
});

it('initializes with empty entities and roots', function () {
    $schema = createTestSchema();
    $ast = Parser::parse('query TestQuery { hello }');

    $context = new NormalizerContext($schema, $ast, null);

    expect($context->entities)->toBe([]);
    expect($context->roots)->toBe([]);
});

function createTestSchema(): Schema
{
    return new Schema([
        'query' => new ObjectType([
            'name' => 'Query',
            'fields' => [
                'hello' => [
                    'type' => Type::string(),
                    'resolve' => fn () => 'world',
                ],
            ],
        ]),
    ]);
}
