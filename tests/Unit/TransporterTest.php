<?php

use ProAI\Transporter\ErrorHandler;
use ProAI\Transporter\Transporter;

it('has correct default static properties', function () {
    expect(Transporter::$enforcedPolicies)->toBeFalse();
    expect(Transporter::$identifierField)->toBe('id');
    expect(Transporter::$normalizedResult)->toBeFalse();
    expect(Transporter::$errorHandler)->toBe(ErrorHandler::class);
});

it('can modify enforcedPolicies', function () {
    $original = Transporter::$enforcedPolicies;

    try {
        Transporter::$enforcedPolicies = true;
        expect(Transporter::$enforcedPolicies)->toBeTrue();
    } finally {
        Transporter::$enforcedPolicies = $original;
    }
});

it('can modify identifierField', function () {
    $original = Transporter::$identifierField;

    try {
        Transporter::$identifierField = 'uuid';
        expect(Transporter::$identifierField)->toBe('uuid');
    } finally {
        Transporter::$identifierField = $original;
    }
});

it('can modify normalizedResult', function () {
    $original = Transporter::$normalizedResult;

    try {
        Transporter::$normalizedResult = true;
        expect(Transporter::$normalizedResult)->toBeTrue();
    } finally {
        Transporter::$normalizedResult = $original;
    }
});

it('is registered in the service container', function () {
    $transporter = app('transporter');

    expect($transporter)->toBeInstanceOf(Transporter::class);
});

it('is aliased to its class name', function () {
    $transporter = app(Transporter::class);

    expect($transporter)->toBeInstanceOf(Transporter::class);
});

it('is a singleton', function () {
    $t1 = app('transporter');
    $t2 = app('transporter');

    expect($t1)->toBe($t2);
});
