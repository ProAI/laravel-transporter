<?php

namespace ProAI\Transporter\Loaders;

use Closure;
use GraphQL\Deferred;
use Illuminate\Support\Traits\Macroable;

class CustomLoader
{
    use Macroable;

    /**
     * The loader closure.
     *
     * @var \Closure
     */
    protected Closure $closure;

    /**
     * Whether the closure has been executed.
     *
     * @var bool
     */
    protected bool $loaded = false;

    /**
     * The cached result.
     *
     * @var mixed
     */
    protected mixed $result = null;

    /**
     * Create a new custom loader instance.
     *
     * @param  \Closure  $closure
     * @return void
     */
    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    /**
     * Load the result.
     *
     * @return \GraphQL\Deferred
     */
    public function asyncLoad(): Deferred
    {
        return new Deferred(function () {
            if (! $this->loaded) {
                $closure = $this->closure;

                $this->result = $closure();
                $this->loaded = true;
            }

            return $this->result;
        });
    }
}
