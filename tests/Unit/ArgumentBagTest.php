<?php

use Illuminate\Support\Collection;
use ProAI\Transporter\ArgumentBag;

it('creates a bag from flat arguments', function () {
    $bag = new ArgumentBag(['name' => 'John', 'age' => 30]);

    expect($bag->get('name'))->toBe('John');
    expect($bag->get('age'))->toBe(30);
});

it('recursively wraps nested arrays as ArgumentBag instances', function () {
    $bag = new ArgumentBag([
        'user' => ['name' => 'John', 'address' => ['city' => 'Berlin']],
    ]);

    expect($bag->get('user'))->toBeInstanceOf(ArgumentBag::class);
    expect($bag->get('user')->get('name'))->toBe('John');
    expect($bag->get('user')->get('address'))->toBeInstanceOf(ArgumentBag::class);
    expect($bag->get('user')->get('address')->get('city'))->toBe('Berlin');
});

it('returns all items as plain arrays recursively', function () {
    $bag = new ArgumentBag([
        'name' => 'John',
        'meta' => ['key' => 'value'],
    ]);

    $all = $bag->all();

    expect($all)->toBe([
        'name' => 'John',
        'meta' => ['key' => 'value'],
    ]);
});

it('creates an empty bag from empty iterable', function () {
    $bag = new ArgumentBag([]);

    expect($bag->all())->toBe([]);
    expect($bag->count())->toBe(0);
});

it('creates an empty bag from empty array', function () {
    $bag = new ArgumentBag([]);

    expect($bag->all())->toBe([]);
});

it('preserves non-array scalar values', function () {
    $bag = new ArgumentBag([
        'string' => 'hello',
        'int' => 42,
        'float' => 3.14,
        'bool' => true,
        'null' => null,
    ]);

    expect($bag->get('string'))->toBe('hello');
    expect($bag->get('int'))->toBe(42);
    expect($bag->get('float'))->toBe(3.14);
    expect($bag->get('bool'))->toBeTrue();
    expect($bag->get('null'))->toBeNull();
});

it('extends Laravel Collection', function () {
    $bag = new ArgumentBag(['a' => 1, 'b' => 2]);

    expect($bag)->toBeInstanceOf(Collection::class);
    expect($bag->count())->toBe(2);
    expect($bag->has('a'))->toBeTrue();
    expect($bag->has('c'))->toBeFalse();
});

it('handles deeply nested structures in all()', function () {
    $bag = new ArgumentBag([
        'level1' => [
            'level2' => [
                'level3' => 'deep',
            ],
        ],
    ]);

    expect($bag->all())->toBe([
        'level1' => [
            'level2' => [
                'level3' => 'deep',
            ],
        ],
    ]);
});
