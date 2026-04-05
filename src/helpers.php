<?php

use ProAI\Transporter\FieldException;

if (! function_exists('field_error')) {
    /**
     * Throw a FieldException with the given data.
     *
     * @param  string  $message
     * @param  string  $code
     * @return never
     *
     * @throws \ProAI\Transporter\FieldException
     */
    function field_error(string $message, string $code = FieldException::DEFAULT_CODE): never
    {
        throw new FieldException($message, $code);
    }
}
