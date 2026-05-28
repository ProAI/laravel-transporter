<?php

use GraphQL\Deferred;
use GraphQL\Executor\Promise\Adapter\SyncPromiseQueue;
use ProAI\Transporter\Loaders\CustomLoader;

it('creates a custom loader with a closure', function () {
    $loader = new CustomLoader(fn () => 'foo');

    expect($loader)->toBeInstanceOf(CustomLoader::class);
});

it('returns a deferred from asyncLoad', function () {
    $loader = new CustomLoader(fn () => 'foo');

    $deferred = $loader->asyncLoad();

    expect($deferred)->toBeInstanceOf(Deferred::class);
});

it('resolves the deferred with the closure result', function () {
    $loader = new CustomLoader(fn () => ['a', 'b', 'c']);

    $deferred = $loader->asyncLoad();
    SyncPromiseQueue::run();

    expect($deferred->result)->toBe(['a', 'b', 'c']);
});

it('invokes the closure only once across multiple asyncLoad calls', function () {
    $calls = 0;

    $loader = new CustomLoader(function () use (&$calls) {
        $calls++;

        return 'result';
    });

    $first = $loader->asyncLoad();
    $second = $loader->asyncLoad();
    SyncPromiseQueue::run();

    expect($calls)->toBe(1);
    expect($first->result)->toBe('result');
    expect($second->result)->toBe('result');
});
