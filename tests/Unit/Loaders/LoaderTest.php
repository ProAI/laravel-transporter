<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use ProAI\Transporter\Loaders\Loader;
use ProAI\Transporter\ModelCache;

it('creates a loader with class name', function () {
    $loader = new Loader(Model::class);

    expect($loader)->toBeInstanceOf(Loader::class);
});

it('sets cache and returns self', function () {
    $loader = new Loader(Model::class);
    $cache = new ModelCache;

    $result = $loader->setCache($cache);

    expect($result)->toBe($loader);
});

it('creates model instance from class', function () {
    $loader = new Loader(User::class);

    $model = $loader->createModel();

    expect($model)->toBeInstanceOf(User::class);
});

it('dispatches without error when no keys are batched', function () {
    $loader = new Loader(Model::class);
    $loader->setCache(new ModelCache);

    // Should not throw
    $loader->dispatch();

    expect(true)->toBeTrue();
});
