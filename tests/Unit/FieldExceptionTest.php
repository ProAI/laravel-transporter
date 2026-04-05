<?php

use GraphQL\Error\ClientAware;
use ProAI\Transporter\FieldException;

it('creates a field exception with default code', function () {
    $exception = new FieldException('Something went wrong');

    expect($exception->getMessage())->toBe('Something went wrong');
    expect($exception->getCode())->toBe('BAD_USER_INPUT');
    expect($exception->properties)->toBeNull();
});

it('creates a field exception with custom code', function () {
    $exception = new FieldException('Not found', 'NOT_FOUND');

    expect($exception->getMessage())->toBe('Not found');
    expect($exception->getCode())->toBe('NOT_FOUND');
});

it('creates a field exception with properties', function () {
    $exception = new FieldException('Invalid', 'BAD_USER_INPUT', 'field_name');

    expect($exception->properties)->toBe('field_name');
});

it('is client safe', function () {
    $exception = new FieldException('Error');

    expect($exception->isClientSafe())->toBeTrue();
});

it('implements ClientAware interface', function () {
    $exception = new FieldException('Error');

    expect($exception)->toBeInstanceOf(ClientAware::class);
});

it('has the correct default code constant', function () {
    expect(FieldException::DEFAULT_CODE)->toBe('BAD_USER_INPUT');
});
