<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use ProAI\Transporter\Shield;
use ProAI\Transporter\ShieldsAttributes;

it('sets and gets a shield', function () {
    $model = new ShieldTestModel;
    $shield = Shield::whitelist(['name']);

    $model->setShield($shield);

    expect($model->getShield())->toBe($shield);
});

it('returns null when no shield is set', function () {
    $model = new ShieldTestModel;

    expect($model->getShield())->toBeNull();
});

it('unsets a shield', function () {
    $model = new ShieldTestModel;
    $model->setShield(Shield::whitelist(['name']));

    $model->unsetShield();

    expect($model->getShield())->toBeNull();
});

it('authorizes attribute when no shield is set', function () {
    $model = new ShieldTestModel;
    $model->forceFill(['name' => 'John', 'secret' => 'data']);

    // Should not throw
    expect($model->getAttributeValue('secret'))->toBe('data');
});

it('allows whitelisted attributes', function () {
    $model = new ShieldTestModel;
    $model->forceFill(['name' => 'John']);
    $model->setShield(Shield::whitelist(['name']));

    expect($model->getAttributeValue('name'))->toBe('John');
});

it('denies non-whitelisted attributes', function () {
    $model = new ShieldTestModel;
    $model->forceFill(['name' => 'John', 'secret' => 'data']);
    $model->setShield(Shield::whitelist(['name']));

    $model->getAttributeValue('secret');
})->throws(AuthorizationException::class);

it('always allows the model key', function () {
    $model = new ShieldTestModel;
    $model->id = 1;
    $model->setShield(Shield::whitelist(['name']));

    // The key name 'id' should always be allowed
    expect($model->getAttributeValue('id'))->toBe(1);
});

it('does not throw for relation when no shield set', function () {
    $model = new ShieldTestModel;

    // Should not throw
    $model->authorizeRelation('posts');
    expect(true)->toBeTrue();
});

it('throws for denied relation', function () {
    $model = new ShieldTestModel;
    $model->setShield(Shield::whitelist([], ['posts']));

    $model->authorizeRelation('comments');
})->throws(AuthorizationException::class);

it('allows whitelisted relation', function () {
    $model = new ShieldTestModel;
    $model->setShield(Shield::whitelist([], ['posts']));

    // Should not throw
    $model->authorizeRelation('posts');
    expect(true)->toBeTrue();
});

class ShieldTestModel extends Model
{
    use ShieldsAttributes;

    protected $guarded = [];

    public $timestamps = false;

    protected $table = 'shield_test';
}
