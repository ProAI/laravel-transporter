<?php

namespace ProAI\Transporter\Connection;

use Illuminate\Pagination\Cursor;

class Edge
{
    /**
     * The node instance.
     *
     * @var mixed
     */
    public mixed $node;

    /**
     * The cursor instance.
     *
     * @var \Illuminate\Pagination\Cursor
     */
    protected Cursor $cursor;

    /**
     * Additional data related to the connection
     *
     * @var array
     */
    protected array $extensions;

    /**
     * Create a new edge instance.
     *
     * @param  mixed  $node
     * @param  \Illuminate\Pagination\Cursor  $cursor
     * @param  array  $extensions
     * @return void
     */
    public function __construct(mixed $node, Cursor $cursor, array $extensions = [])
    {
        $this->node = $node;
        $this->cursor = $cursor;

        $this->extensions = $extensions;
    }

    /**
     * Get the cursor.
     *
     * @return string
     */
    public function cursor(): string
    {
        return $this->cursor->encode();
    }

    /**
     * Dynamically retrieve extensions.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        if (isset($this->extensions[$key])) {
            return $this->extensions[$key];
        }

        return null;
    }
}
