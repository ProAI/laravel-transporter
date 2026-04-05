<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use ProAI\Transporter\Shield;

it('creates a whitelist shield', function () {
    $shield = Shield::whitelist(['name', 'email'], ['posts']);

    expect($shield)->toBeInstanceOf(Shield::class);
    expect($shield->allowedForAttribute('name'))->toBeTrue();
    expect($shield->allowedForAttribute('email'))->toBeTrue();
    expect($shield->allowedForAttribute('secret'))->toBeFalse();
    expect($shield->allowedForRelation('posts'))->toBeTrue();
    expect($shield->allowedForRelation('comments'))->toBeFalse();
});

it('creates a blacklist shield', function () {
    $shield = Shield::blacklist(['secret'], ['hidden']);

    expect($shield->allowedForAttribute('name'))->toBeTrue();
    expect($shield->allowedForAttribute('secret'))->toBeFalse();
    expect($shield->allowedForRelation('posts'))->toBeTrue();
    expect($shield->allowedForRelation('hidden'))->toBeFalse();
});

it('allows all attributes when no whitelist or blacklist is set', function () {
    $shield = new Shield;

    expect($shield->allowedForAttribute('anything'))->toBeTrue();
    expect($shield->allowedForRelation('anything'))->toBeTrue();
});

it('sets whitelisted attributes and relations', function () {
    $shield = new Shield;
    $result = $shield->setWhitelisted(['name'], ['posts']);

    expect($result)->toBe($shield);
    expect($shield->allowedForAttribute('name'))->toBeTrue();
    expect($shield->allowedForAttribute('other'))->toBeFalse();
    expect($shield->allowedForRelation('posts'))->toBeTrue();
    expect($shield->allowedForRelation('other'))->toBeFalse();
});

it('sets blacklisted attributes and relations', function () {
    $shield = new Shield;
    $result = $shield->setBlacklisted(['secret'], ['hidden']);

    expect($result)->toBe($shield);
    expect($shield->allowedForAttribute('name'))->toBeTrue();
    expect($shield->allowedForAttribute('secret'))->toBeFalse();
    expect($shield->allowedForRelation('posts'))->toBeTrue();
    expect($shield->allowedForRelation('hidden'))->toBeFalse();
});

it('reports denied for attribute correctly', function () {
    $shield = Shield::whitelist(['name']);

    expect($shield->deniedForAttribute('name'))->toBeFalse();
    expect($shield->deniedForAttribute('secret'))->toBeTrue();
});

it('reports denied for relation correctly', function () {
    $shield = Shield::whitelist([], ['posts']);

    expect($shield->deniedForRelation('posts'))->toBeFalse();
    expect($shield->deniedForRelation('hidden'))->toBeTrue();
});

it('throws AuthorizationException for denied attribute', function () {
    $shield = Shield::whitelist(['name']);

    $shield->authorizeForAttribute('secret');
})->throws(AuthorizationException::class);

it('returns self when attribute is allowed', function () {
    $shield = Shield::whitelist(['name']);

    $result = $shield->authorizeForAttribute('name');

    expect($result)->toBe($shield);
});

it('throws AuthorizationException for denied relation', function () {
    $shield = Shield::whitelist([], ['posts']);

    $shield->authorizeForRelation('hidden');
})->throws(AuthorizationException::class);

it('returns self when relation is allowed', function () {
    $shield = Shield::whitelist([], ['posts']);

    $result = $shield->authorizeForRelation('posts');

    expect($result)->toBe($shield);
});

it('uses default message for denied attribute', function () {
    $shield = Shield::whitelist(['name']);

    try {
        $shield->authorizeForAttribute('secret');
    } catch (AuthorizationException $e) {
        expect($e->getMessage())->toBe('This action is unauthorized for attribute "secret".');

        return;
    }

    $this->fail('Expected exception was not thrown');
});

it('uses custom message for denied attribute', function () {
    $shield = Shield::whitelist(['name']);
    $shield->setMessage('Custom denied');

    try {
        $shield->authorizeForAttribute('secret');
    } catch (AuthorizationException $e) {
        expect($e->getMessage())->toBe('Custom denied');

        return;
    }

    $this->fail('Expected exception was not thrown');
});

it('uses default message for denied relation', function () {
    $shield = Shield::whitelist([], ['posts']);

    try {
        $shield->authorizeForRelation('hidden');
    } catch (AuthorizationException $e) {
        expect($e->getMessage())->toBe('This action is unauthorized for relation "hidden".');

        return;
    }

    $this->fail('Expected exception was not thrown');
});

it('uses custom message for denied relation', function () {
    $shield = Shield::whitelist([], ['posts']);
    $shield->setMessage('Custom message');

    try {
        $shield->authorizeForRelation('hidden');
    } catch (AuthorizationException $e) {
        expect($e->getMessage())->toBe('Custom message');

        return;
    }

    $this->fail('Expected exception was not thrown');
});

it('sets custom code on the shield', function () {
    $shield = Shield::whitelist(['name']);
    $shield->setCode('CUSTOM_CODE');

    try {
        $shield->authorizeForAttribute('secret');
    } catch (AuthorizationException $e) {
        expect($e->getCode())->toBe('CUSTOM_CODE');

        return;
    }

    $this->fail('Expected exception was not thrown');
});

it('disables shields within callback', function () {
    $shield = Shield::whitelist(['name']);

    $result = Shield::disableFor(function () use ($shield) {
        return $shield->allowedForAttribute('secret');
    });

    expect($result)->toBeTrue();
});

it('re-enables shields after callback', function () {
    $shield = Shield::whitelist(['name']);

    Shield::disableFor(function () {
        // Shields disabled here
    });

    expect($shield->allowedForAttribute('secret'))->toBeFalse();
});

it('re-enables shields even if callback throws', function () {
    $shield = Shield::whitelist(['name']);

    try {
        Shield::disableFor(function () {
            throw new RuntimeException('Test error');
        });
    } catch (RuntimeException) {
        // Expected
    }

    expect($shield->allowedForAttribute('secret'))->toBeFalse();
});

it('returns callback result from disableFor', function () {
    $result = Shield::disableFor(function () {
        return 'test-value';
    });

    expect($result)->toBe('test-value');
});

it('extends Response class', function () {
    $shield = new Shield;

    expect($shield)->toBeInstanceOf(Response::class);
});

it('creates whitelist with default empty relations', function () {
    $shield = Shield::whitelist(['name']);

    expect($shield->allowedForRelation('anything'))->toBeFalse();
});

it('creates blacklist with default empty relations', function () {
    $shield = Shield::blacklist(['secret']);

    expect($shield->allowedForRelation('anything'))->toBeTrue();
});
