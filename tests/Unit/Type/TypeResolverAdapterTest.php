<?php

use ProAI\Transporter\Type\TypeResolverAdapter;

it('creates an adapter with a callback', function () {
    $adapter = new TypeResolverAdapter(fn () => 'test');

    expect($adapter)->toBeInstanceOf(TypeResolverAdapter::class);
});

it('creates an adapter with a class string', function () {
    $adapter = new TypeResolverAdapter('App\Resolvers\TypeResolver');

    expect($adapter)->toBeInstanceOf(TypeResolverAdapter::class);
});
