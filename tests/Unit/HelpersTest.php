<?php

use ProAI\Transporter\FieldException;

it('throws a FieldException with default code', function () {
    field_error('Something went wrong');
})->throws(FieldException::class, 'Something went wrong');

it('throws a FieldException with custom code', function () {
    try {
        field_error('Not found', 'NOT_FOUND');
    } catch (FieldException $e) {
        expect($e->getMessage())->toBe('Not found');
        expect($e->getCode())->toBe('NOT_FOUND');

        return;
    }

    $this->fail('Expected FieldException was not thrown');
});
