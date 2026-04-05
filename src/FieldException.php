<?php

namespace ProAI\Transporter;

use Exception;
use GraphQL\Error\ClientAware;

class FieldException extends Exception implements ClientAware
{
    /**
     * The default code.
     *
     * @var string
     */
    const DEFAULT_CODE = 'BAD_USER_INPUT';

    /**
     * The exception properties of the GraphQL field error.
     *
     * @var string
     */
    public mixed $properties;

    /**
     * Create a new field exception instance.
     *
     * @param  string  $message
     * @param  string  $code
     * @param  string  $properties
     * @return void
     */
    public function __construct(string $message, string $code = self::DEFAULT_CODE, mixed $properties = null)
    {
        parent::__construct($message);

        $this->code = $code;
        $this->properties = $properties;
    }

    /**
     * Is it safe to show the error message to clients?
     *
     * @return bool
     */
    public function isClientSafe(): bool
    {
        return true;
    }
}
