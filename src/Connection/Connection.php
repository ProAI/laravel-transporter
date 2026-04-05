<?php

namespace ProAI\Transporter\Connection;

use Illuminate\Pagination\CursorPaginator;

/** @phpstan-consistent-constructor */
class Connection
{
    /**
     * The paginator instance.
     *
     * @var \Illuminate\Pagination\CursorPaginator
     */
    public CursorPaginator $paginator;

    /**
     * Additional data related to the connection
     *
     * @var array
     */
    protected array $extensions;

    /**
     * Create a new connection instance.
     *
     * @param  \Illuminate\Pagination\CursorPaginator  $paginator
     * @param  array  $extensions
     * @return void
     */
    public function __construct(CursorPaginator $paginator, array $extensions = [])
    {
        $this->paginator = $paginator;

        $this->extensions = $extensions;
    }

    /**
     * Create a new connection.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $query
     * @param  int  $perPage
     * @param  string|null  $cursor
     * @param  bool  $pointsToNextItems
     * @return static
     */
    public static function make(mixed $query, int $perPage, ?string $cursor, bool $pointsToNextItems): static
    {
        $cursor = Cursor::fromInput($query, $cursor, $pointsToNextItems);

        $paginator = $query->cursorPaginate(
            $perPage,
            ['*'],
            'cursor',
            $cursor
        );

        return new static($paginator);
    }

    /**
     * Check if connection has next page.
     *
     * @return array
     */
    public function edges(): array
    {
        return array_map(function (mixed $item) {
            $cursor = Cursor::forItem($item, $this->paginator);

            return new Edge($item, $cursor);
        }, $this->paginator->items());
    }

    /**
     * Check if connection has next page.
     *
     * @return array
     */
    public function nodes(): array
    {
        return $this->paginator->items();
    }

    /**
     * Check if connection has next page.
     *
     * @return \ProAI\Transporter\Connection\PageInfo
     */
    public function pageInfo(): PageInfo
    {
        return new PageInfo($this->paginator);
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
