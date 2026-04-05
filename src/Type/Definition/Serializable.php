<?php

namespace ProAI\Transporter\Type\Definition;

trait Serializable
{
    /**
     * Prepare the instance for serialization.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return $this->config;
    }

    /**
     * Restore the type after serialization.
     *
     * @param  array  $config
     * @return void
     */
    public function __unserialize(array $config): void
    {
        parent::__construct($config);
    }
}
