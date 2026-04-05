<?php

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use ProAI\Transporter\Type\FieldResolverAdapter;

it('creates an adapter with a callback', function () {
    $adapter = new FieldResolverAdapter(fn () => 'test');

    expect($adapter)->toBeInstanceOf(FieldResolverAdapter::class);
});

it('creates an adapter with a class string', function () {
    $adapter = new FieldResolverAdapter('App\Resolvers\UserResolver');

    expect($adapter)->toBeInstanceOf(FieldResolverAdapter::class);
});

it('sets resolver on field definition', function () {
    $type = new ObjectType([
        'name' => 'Test',
        'fields' => [
            'test' => ['type' => Type::string()],
        ],
    ]);

    $field = $type->getField('test');

    FieldResolverAdapter::forField($field, fn () => 'resolved');

    expect($field->resolveFn)->toBeInstanceOf(FieldResolverAdapter::class);
});
