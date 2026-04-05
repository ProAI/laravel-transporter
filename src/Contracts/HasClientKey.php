<?php

namespace ProAI\Transporter\Contracts;

interface HasClientKey
{
    /**
     * Get the value of the model's client key.
     *
     * @return mixed
     */
    public function getClientKey(): mixed;

    /**
     * Get the client key for the model.
     *
     * @return string
     */
    public function getClientKeyName(): string;
}
