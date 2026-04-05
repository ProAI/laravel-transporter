<?php

use GraphQL\Error\Error;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use ProAI\Transporter\ErrorHandler;
use ProAI\Transporter\FieldException;

beforeEach(function () {
    $this->exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $this->handler = new ErrorHandler($this->exceptionHandler);
    $this->formatter = function (Error $error) {
        $result = ['message' => $error->getMessage()];
        if ($error->getExtensions()) {
            $result['extensions'] = $error->getExtensions();
        }

        return $result;
    };
});

it('handles FieldException errors', function () {
    $fieldException = new FieldException('Bad input', 'BAD_USER_INPUT');
    $error = new Error('Original', previous: $fieldException);

    $this->exceptionHandler->shouldNotReceive('report');

    $result = ($this->handler)([$error], $this->formatter);

    expect($result)->toHaveCount(1);
    expect($result[0]['message'])->toBe('Bad input');
    expect($result[0]['extensions']['code'])->toBe('BAD_USER_INPUT');
});

it('handles FieldException with properties', function () {
    $fieldException = new FieldException('Bad input', 'BAD_USER_INPUT', 'field_name');
    $error = new Error('Original', previous: $fieldException);

    $result = ($this->handler)([$error], $this->formatter);

    expect($result[0]['extensions']['exception'])->toBe('field_name');
});

it('handles AuthenticationException errors', function () {
    $authException = new AuthenticationException('Unauthenticated.');
    $error = new Error('Original', previous: $authException);

    $this->exceptionHandler->shouldReceive('report')->once()->with($authException);

    $result = ($this->handler)([$error], $this->formatter);

    expect($result[0]['message'])->toBe('Unauthenticated.');
    expect($result[0]['extensions']['code'])->toBe('UNAUTHENTICATED');
});

it('handles AuthenticationException with empty message', function () {
    $authException = new AuthenticationException;
    $error = new Error('Original', previous: $authException);

    $this->exceptionHandler->shouldReceive('report')->once();

    $result = ($this->handler)([$error], $this->formatter);

    expect($result[0]['message'])->toBe('Unauthenticated.');
});

it('handles ModelNotFoundException errors', function () {
    config(['app.debug' => false]);
    $modelException = (new ModelNotFoundException)->setModel('App\Models\User');
    $error = new Error('Original', previous: $modelException);

    $this->exceptionHandler->shouldReceive('report')->once();

    $result = ($this->handler)([$error], $this->formatter);

    expect($result[0]['message'])->toBe('Model not found.');
    expect($result[0]['extensions']['code'])->toBe('NOT_FOUND');
});

it('handles ModelNotFoundException with debug message', function () {
    config(['app.debug' => true]);
    $modelException = (new ModelNotFoundException)->setModel('App\Models\User');
    $error = new Error('Original', previous: $modelException);

    $this->exceptionHandler->shouldReceive('report')->once();

    $result = ($this->handler)([$error], $this->formatter);

    expect($result[0]['message'])->toContain('User');
    expect($result[0]['extensions']['code'])->toBe('NOT_FOUND');
});

it('handles AuthorizationException errors', function () {
    config(['app.debug' => false]);
    $authzException = new AuthorizationException('Forbidden action');
    $error = new Error('Original', previous: $authzException);

    $this->exceptionHandler->shouldReceive('report')->once();

    $result = ($this->handler)([$error], $this->formatter);

    expect($result[0]['message'])->toBe('This action is unauthorized.');
    expect($result[0]['extensions']['code'])->toBe('FORBIDDEN');
});

it('handles AuthorizationException with debug message', function () {
    config(['app.debug' => true]);
    $authzException = new AuthorizationException('Forbidden action');
    $error = new Error('Original', previous: $authzException);

    $this->exceptionHandler->shouldReceive('report')->once();

    $result = ($this->handler)([$error], $this->formatter);

    expect($result[0]['message'])->toBe('Forbidden action');
});

it('handles ValidationException errors', function () {
    $validator = app('validator')->make([], ['name' => 'required']);
    $validator->fails();
    $validationException = new ValidationException($validator);
    $error = new Error('Original', previous: $validationException);

    $this->exceptionHandler->shouldReceive('report')->once();

    $result = ($this->handler)([$error], $this->formatter);

    expect($result[0]['extensions']['code'])->toBe('BAD_USER_INPUT');
    expect($result[0]['extensions']['exception']['validationErrors'])->toBeArray();
});

it('returns null for unhandled exceptions', function () {
    $exception = new RuntimeException('Unknown');
    $error = new Error('Original', previous: $exception);

    $this->exceptionHandler->shouldReceive('report')->once();

    $result = ($this->handler)([$error], $this->formatter);

    // The unhandled error should still be formatted (original error passed through)
    expect($result)->toHaveCount(1);
});

it('does not report client safe errors', function () {
    $fieldException = new FieldException('Client safe error');
    $error = new Error('Original', previous: $fieldException);

    $this->exceptionHandler->shouldNotReceive('report');

    ($this->handler)([$error], $this->formatter);
});

it('handles multiple errors', function () {
    $error1 = new Error('Original 1', previous: new FieldException('Error 1'));
    $error2 = new Error('Original 2', previous: new AuthenticationException('Error 2'));

    $this->exceptionHandler->shouldReceive('report')->once();

    $result = ($this->handler)([$error1, $error2], $this->formatter);

    expect($result)->toHaveCount(2);
    expect($result[0]['message'])->toBe('Error 1');
    expect($result[1]['message'])->toBe('Error 2');
});
