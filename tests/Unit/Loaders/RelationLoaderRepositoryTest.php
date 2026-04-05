<?php

use Illuminate\Database\Eloquent\Model;
use ProAI\Transporter\Loaders\AggregateLoader;
use ProAI\Transporter\Loaders\RelationLoader;
use ProAI\Transporter\Loaders\RelationLoaderRepository;
use ProAI\Transporter\ModelCache;

it('creates a repository', function () {
    $repo = new RelationLoaderRepository('App\Models\User', 'posts');

    expect($repo)->toBeInstanceOf(RelationLoaderRepository::class);
});

it('sets cache and returns self', function () {
    $repo = new RelationLoaderRepository('App\Models\User', 'posts');
    $cache = new ModelCache;

    $result = $repo->setCache($cache);

    expect($result)->toBe($repo);
});

it('returns the same relation loader instance', function () {
    $repo = new RelationLoaderRepository(
        Model::class,
        'relation'
    );
    $repo->setCache(new ModelCache);

    $loader1 = $repo->getRelationLoader();
    $loader2 = $repo->getRelationLoader();

    expect($loader1)->toBeInstanceOf(RelationLoader::class);
    expect($loader1)->toBe($loader2);
});

it('returns the same aggregate loader for same function and column', function () {
    $repo = new RelationLoaderRepository(
        Model::class,
        'relation'
    );

    $loader1 = $repo->getAggregateLoader('count', '*');
    $loader2 = $repo->getAggregateLoader('count', '*');

    expect($loader1)->toBeInstanceOf(AggregateLoader::class);
    expect($loader1)->toBe($loader2);
});

it('returns different aggregate loaders for different functions', function () {
    $repo = new RelationLoaderRepository(
        Model::class,
        'relation'
    );

    $countLoader = $repo->getAggregateLoader('count', '*');
    $sumLoader = $repo->getAggregateLoader('sum', 'amount');

    expect($countLoader)->not->toBe($sumLoader);
});
