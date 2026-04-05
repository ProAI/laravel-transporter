<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use ProAI\Transporter\Context;
use ProAI\Transporter\Loaders\Loader;
use ProAI\Transporter\ModelCache;

it('creates a context with model cache', function () {
    $container = app();
    $context = new Context($container);

    expect($context->getModelCache())->toBeInstanceOf(ModelCache::class);
});

it('returns a loader instance', function () {
    $container = app();
    $context = new Context($container);

    $loader = $context->loader(Model::class);

    expect($loader)->toBeInstanceOf(Loader::class);
});

it('returns the same loader instance for the same class', function () {
    $container = app();
    $context = new Context($container);

    $loader1 = $context->loader(Model::class);
    $loader2 = $context->loader(Model::class);

    expect($loader1)->toBe($loader2);
});

it('returns different loader instances for different classes', function () {
    $container = app();
    $context = new Context($container);

    $loader1 = $context->loader(Model::class);
    $loader2 = $context->loader(User::class);

    expect($loader1)->not->toBe($loader2);
});

it('registers a filter', function () {
    $container = app();
    $context = new Context($container);

    $context->registerFilter('test', function ($query) {
        return $query;
    });

    // No exception means success
    expect(true)->toBeTrue();
});

it('throws when registering a duplicate filter', function () {
    $container = app();
    $context = new Context($container);

    $context->registerFilter('test', function ($query) {
        return $query;
    });

    $context->registerFilter('test', function ($query) {
        return $query;
    });
})->throws(InvalidArgumentException::class);
