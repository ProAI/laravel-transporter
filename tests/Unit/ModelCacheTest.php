<?php

use Illuminate\Database\Eloquent\Model;
use ProAI\Transporter\ModelCache;

beforeEach(function () {
    $this->cache = new ModelCache;
});

it('returns null when model is not cached', function () {
    expect($this->cache->get(TestCacheModel::class, 1))->toBeNull();
});

it('stores and retrieves a model by key', function () {
    $model = new TestCacheModel;
    $model->id = 1;
    $model->name = 'Test';

    $this->cache->add($model);

    $cached = $this->cache->get(TestCacheModel::class, 1);

    expect($cached)->toBeInstanceOf(TestCacheModel::class);
    expect($cached->id)->toBe(1);
    expect($cached->name)->toBe('Test');
});

it('returns a clone of the cached model', function () {
    $model = new TestCacheModel;
    $model->id = 1;

    $this->cache->add($model);

    $cached = $this->cache->get(TestCacheModel::class, 1);

    expect($cached)->not->toBe($model);
    expect($cached->id)->toBe($model->id);
});

it('stores a clone of the model in cache', function () {
    $model = new TestCacheModel;
    $model->id = 1;
    $model->name = 'Original';

    $this->cache->add($model);

    $model->name = 'Modified';

    $cached = $this->cache->get(TestCacheModel::class, 1);
    expect($cached->name)->toBe('Original');
});

it('retrieves a model using a closure', function () {
    $model = new TestCacheModel;
    $model->id = 1;
    $model->name = 'FindMe';

    $this->cache->add($model);

    $cached = $this->cache->get(TestCacheModel::class, function ($item) {
        return $item->name === 'FindMe';
    });

    expect($cached)->toBeInstanceOf(TestCacheModel::class);
    expect($cached->name)->toBe('FindMe');
});

it('returns null when closure does not match', function () {
    $model = new TestCacheModel;
    $model->id = 1;
    $model->name = 'Test';

    $this->cache->add($model);

    $cached = $this->cache->get(TestCacheModel::class, function ($item) {
        return $item->name === 'NonExistent';
    });

    expect($cached)->toBeNull();
});

it('stores multiple models of the same class', function () {
    $model1 = new TestCacheModel;
    $model1->id = 1;
    $model1->name = 'First';

    $model2 = new TestCacheModel;
    $model2->id = 2;
    $model2->name = 'Second';

    $this->cache->add($model1);
    $this->cache->add($model2);

    expect($this->cache->get(TestCacheModel::class, 1)->name)->toBe('First');
    expect($this->cache->get(TestCacheModel::class, 2)->name)->toBe('Second');
});

it('isolates models by class', function () {
    $model = new TestCacheModel;
    $model->id = 1;

    $this->cache->add($model);

    expect($this->cache->get(AnotherTestCacheModel::class, 1))->toBeNull();
});

// Test model stubs
class TestCacheModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

class AnotherTestCacheModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
