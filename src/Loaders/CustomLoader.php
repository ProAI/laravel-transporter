<?php

namespace ProAI\Transporter\Loaders;

use Closure;
use GraphQL\Deferred;
use Illuminate\Support\Traits\Macroable;
use RuntimeException;

class CustomLoader
{
    use Macroable;

    /**
     * The batch loading closure.
     *
     * @var \Closure
     */
    protected Closure $closure;

    /**
     * The keys queued for the next batch.
     *
     * @var array
     */
    protected array $keys = [];

    /**
     * The resolved results, indexed by key.
     *
     * @var array
     */
    protected array $results = [];

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
     * Load the value for the given key.
     *
     * @param  string|int  $key
     * @return \GraphQL\Deferred
     */
    public function asyncLoad(string|int $key): Deferred
    {
        if (! array_key_exists($key, $this->results) && ! array_key_exists($key, $this->keys)) {
            $this->keys[$key] = $key;
        }

        return new Deferred(function () use ($key) {
            if (! array_key_exists($key, $this->results)) {
                $this->dispatch();
            }

            return $this->results[$key];
        });
    }

    /**
     * Dispatch loading of all queued keys.
     *
     * @return void
     */
    public function dispatch(): void
    {
        if (empty($this->keys)) {
            return;
        }

        $keys = array_values($this->keys);

        $this->keys = [];

        $values = ($this->closure)($keys);

        if (! is_array($values)) {
            throw new RuntimeException(
                'Custom loader batch closure must return an array of values.'
            );
        }

        $values = array_values($values);

        if (count($values) !== count($keys)) {
            throw new RuntimeException(sprintf(
                'Custom loader batch closure must return an array of the same length as the keys array (expected %d, got %d).',
                count($keys),
                count($values)
            ));
        }

        foreach ($keys as $i => $key) {
            $this->results[$key] = $values[$i];
        }
    }
}
