<?php

namespace ProAI\Transporter\Connection;

use Illuminate\Pagination\CursorPaginator;

class PageInfo
{
    /**
     * The paginator instance.
     *
     * @var \Illuminate\Pagination\CursorPaginator
     */
    public CursorPaginator $paginator;

    /**
     * Create a new page info instance.
     *
     * @param  \Illuminate\Pagination\CursorPaginator  $paginator
     * @return void
     */
    public function __construct(CursorPaginator $paginator)
    {
        $this->paginator = $paginator;
    }

    /**
     * Check if connection has previous page.
     *
     * @return bool
     */
    public function hasPreviousPage(): bool
    {
        return (bool) $this->paginator->previousCursor();
    }

    /**
     * Check if connection has next page.
     *
     * @return bool
     */
    public function hasNextPage(): bool
    {
        return (bool) $this->paginator->nextCursor();
    }

    /**
     * Get the cursor of the first item of the page.
     *
     * @return string
     */
    public function startCursor(): ?string
    {
        $items = collect($this->paginator->items());
        $firstItem = $items->first();

        if (! $firstItem) {
            return null;
        }

        return Cursor::forItem($firstItem, $this->paginator)->encode();
    }

    /**
     * Get the cursor of the last item of the page.
     *
     * @return string
     */
    public function endCursor(): ?string
    {
        $items = collect($this->paginator->items());
        $lastItem = $items->last();

        if (! $lastItem) {
            return null;
        }

        return Cursor::forItem($lastItem, $this->paginator)->encode();
    }
}
