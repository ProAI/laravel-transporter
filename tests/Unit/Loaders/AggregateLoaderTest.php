<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use ProAI\Transporter\Loaders\AggregateLoader;

it('generates correct attribute key for count', function () {
    $loader = new AggregateLoader(
        Model::class,
        'posts',
        '*',
        'count'
    );

    $key = $loader->getAttributeKey('posts', '*', 'count');

    expect($key)->toBe('posts_count');
});

it('generates correct attribute key for sum', function () {
    $loader = new AggregateLoader(
        Model::class,
        'orders',
        'amount',
        'sum'
    );

    $key = $loader->getAttributeKey('orders', 'amount', 'sum');

    expect($key)->toBe('orders_sum_amount');
});

it('generates correct attribute key for avg', function () {
    $loader = new AggregateLoader(
        Model::class,
        'reviews',
        'rating',
        'avg'
    );

    $key = $loader->getAttributeKey('reviews', 'rating', 'avg');

    expect($key)->toBe('reviews_avg_rating');
});

it('generates correct attribute key for min', function () {
    $loader = new AggregateLoader(
        Model::class,
        'products',
        'price',
        'min'
    );

    $key = $loader->getAttributeKey('products', 'price', 'min');

    expect($key)->toBe('products_min_price');
});

it('generates correct attribute key for max', function () {
    $loader = new AggregateLoader(
        Model::class,
        'products',
        'price',
        'max'
    );

    $key = $loader->getAttributeKey('products', 'price', 'max');

    expect($key)->toBe('products_max_price');
});

it('dispatches without error when no items are batched', function () {
    $loader = new AggregateLoader(
        Model::class,
        'posts',
        '*',
        'count'
    );

    // Should not throw
    $loader->dispatch();

    expect(true)->toBeTrue();
});

it('throws when model class does not match', function () {
    $loader = new AggregateLoader(
        User::class,
        'posts',
        '*',
        'count'
    );

    $model = new class extends Model
    {
        protected $guarded = [];
    };

    $loader->asyncLoadFrom($model);
})->throws(InvalidArgumentException::class);
