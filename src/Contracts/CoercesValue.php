<?php

namespace ProAI\Transporter\Contracts;

interface CoercesValue extends SerializesValue
{
    /**
     * @param  mixed  $value
     * @return mixed
     */
    public function parseValue(mixed $value): mixed;
}
