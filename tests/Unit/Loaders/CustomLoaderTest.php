<?php

use GraphQL\Deferred;
use GraphQL\Executor\Promise\Adapter\SyncPromiseQueue;
use ProAI\Transporter\Loaders\CustomLoader;

it('creates a custom loader with a closure', function () {
    $loader = new CustomLoader(fn (array $keys) => $keys);

    expect($loader)->toBeInstanceOf(CustomLoader::class);
});

it('returns a deferred from asyncLoad', function () {
    $loader = new CustomLoader(fn (array $keys) => array_map(fn ($k) => "v:$k", $keys));

    $deferred = $loader->asyncLoad(1);

    expect($deferred)->toBeInstanceOf(Deferred::class);
});

it('resolves the deferred with the value for the given key', function () {
    $loader = new CustomLoader(fn (array $keys) => array_map(fn ($k) => "v:$k", $keys));

    $deferred = $loader->asyncLoad('foo');
    SyncPromiseQueue::run();

    expect($deferred->result)->toBe('v:foo');
});

it('batches multiple asyncLoad calls into a single closure invocation', function () {
    $batches = [];

    $loader = new CustomLoader(function (array $keys) use (&$batches) {
        $batches[] = $keys;

        return array_map(fn ($k) => "v:$k", $keys);
    });

    $a = $loader->asyncLoad('a');
    $b = $loader->asyncLoad('b');
    $c = $loader->asyncLoad('c');
    SyncPromiseQueue::run();

    expect($batches)->toBe([['a', 'b', 'c']]);
    expect($a->result)->toBe('v:a');
    expect($b->result)->toBe('v:b');
    expect($c->result)->toBe('v:c');
});

it('deduplicates identical keys within a batch', function () {
    $batches = [];

    $loader = new CustomLoader(function (array $keys) use (&$batches) {
        $batches[] = $keys;

        return array_map(fn ($k) => "v:$k", $keys);
    });

    $first = $loader->asyncLoad('x');
    $second = $loader->asyncLoad('x');
    SyncPromiseQueue::run();

    expect($batches)->toBe([['x']]);
    expect($first->result)->toBe('v:x');
    expect($second->result)->toBe('v:x');
});

it('caches results across batches', function () {
    $batches = [];

    $loader = new CustomLoader(function (array $keys) use (&$batches) {
        $batches[] = $keys;

        return array_map(fn ($k) => "v:$k", $keys);
    });

    $first = $loader->asyncLoad('x');
    SyncPromiseQueue::run();

    $second = $loader->asyncLoad('x');
    SyncPromiseQueue::run();

    expect($batches)->toBe([['x']]);
    expect($first->result)->toBe('v:x');
    expect($second->result)->toBe('v:x');
});

it('starts a new batch for keys queued after a previous dispatch', function () {
    $batches = [];

    $loader = new CustomLoader(function (array $keys) use (&$batches) {
        $batches[] = $keys;

        return array_map(fn ($k) => "v:$k", $keys);
    });

    $a = $loader->asyncLoad('a');
    SyncPromiseQueue::run();

    $b = $loader->asyncLoad('b');
    SyncPromiseQueue::run();

    expect($batches)->toBe([['a'], ['b']]);
    expect($a->result)->toBe('v:a');
    expect($b->result)->toBe('v:b');
});

it('throws when the closure returns a non-array value', function () {
    $loader = new CustomLoader(fn (array $keys) => 'not-an-array');

    $loader->asyncLoad('a');
    $loader->dispatch();
})->throws(RuntimeException::class);

it('throws when the closure returns an array of a different length', function () {
    $loader = new CustomLoader(fn (array $keys) => ['only-one']);

    $loader->asyncLoad('a');
    $loader->asyncLoad('b');
    $loader->dispatch();
})->throws(RuntimeException::class);
