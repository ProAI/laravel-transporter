<?php

use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use ProAI\Transporter\ArgumentBag;
use ProAI\Transporter\Resolvers\DefaultResolver;
use ProAI\Transporter\Transporter;

it('resolves plain object attributes', function () {
    $resolver = new DefaultResolver;
    $source = (object) ['name' => 'John'];
    $args = new ArgumentBag;
    $info = createDefaultResolverInfo('name');

    $result = $resolver($source, $args, null, $info);

    expect($result)->toBe('John');
});

it('resolves plain object methods', function () {
    $resolver = new DefaultResolver;
    $source = new class
    {
        public function greeting()
        {
            return 'Hello World';
        }
    };
    $args = new ArgumentBag;
    $info = createDefaultResolverInfo('greeting');

    $result = $resolver($source, $args, null, $info);

    expect($result)->toBe('Hello World');
});

it('resolves identifier field for plain objects', function () {
    $resolver = new DefaultResolver;
    $source = (object) ['id' => 42];
    $args = new ArgumentBag;
    $info = createDefaultResolverInfo(Transporter::$identifierField);

    $result = $resolver($source, $args, null, $info);

    expect($result)->toBe(42);
});

it('converts camelCase to snake_case for model attributes', function () {
    $resolver = new DefaultResolver;

    $method = new ReflectionMethod(DefaultResolver::class, 'getAttributeKeyName');

    expect($method->invoke($resolver, 'firstName'))->toBe('first_name');
    expect($method->invoke($resolver, 'createdAt'))->toBe('created_at');
    expect($method->invoke($resolver, 'name'))->toBe('name');
});

function createDefaultResolverInfo(string $fieldName): ResolveInfo
{
    $parentType = new ObjectType([
        'name' => 'Query',
        'fields' => [
            'name' => ['type' => Type::string()],
            'greeting' => ['type' => Type::string()],
            'id' => ['type' => Type::id()],
        ],
    ]);

    $schema = new Schema(['query' => $parentType]);

    $fieldDefinition = $parentType->getField($fieldName);

    $ast = Parser::parse('query Test { '.$fieldName.' }');
    $operation = $ast->definitions[0];

    return new ResolveInfo(
        $fieldDefinition,
        new ArrayObject([$ast->definitions[0]->selectionSet->selections[0]]),
        $parentType,
        [$fieldName],
        $schema,
        [],
        null,
        $operation,
        []
    );
}
