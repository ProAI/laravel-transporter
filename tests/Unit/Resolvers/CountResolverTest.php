<?php

use ProAI\Transporter\Resolvers\CountResolver;

it('strips Count suffix to get relation name', function () {
    $resolver = new CountResolver;

    $method = new ReflectionMethod(CountResolver::class, 'getRelationName');

    $info = (object) ['fieldName' => 'postsCount'];
    expect($method->invoke($resolver, $info))->toBe('posts');

    $info = (object) ['fieldName' => 'commentsCount'];
    expect($method->invoke($resolver, $info))->toBe('comments');
});

it('leaves field name unchanged when no Count suffix', function () {
    $resolver = new CountResolver;

    $method = new ReflectionMethod(CountResolver::class, 'getRelationName');

    $info = (object) ['fieldName' => 'posts'];
    expect($method->invoke($resolver, $info))->toBe('posts');
});
