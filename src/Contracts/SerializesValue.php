<?php

namespace ProAI\Transporter\Contracts;

interface SerializesValue
{
    /**
     * @param  mixed  $value
     * @return mixed
     */
    public function serialize(mixed $value): mixed;
}
