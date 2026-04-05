<?php

use ProAI\Transporter\Resolvers\ConnectionResolver;

it('strips Connection suffix to get relation name', function () {
    $resolver = new ConnectionResolver;

    $method = new ReflectionMethod(ConnectionResolver::class, 'getRelationName');

    $info = (object) ['fieldName' => 'postsConnection'];
    expect($method->invoke($resolver, $info))->toBe('posts');

    $info = (object) ['fieldName' => 'commentsConnection'];
    expect($method->invoke($resolver, $info))->toBe('comments');
});

it('leaves field name unchanged when no Connection suffix', function () {
    $resolver = new ConnectionResolver;

    $method = new ReflectionMethod(ConnectionResolver::class, 'getRelationName');

    $info = (object) ['fieldName' => 'posts'];
    expect($method->invoke($resolver, $info))->toBe('posts');
});
