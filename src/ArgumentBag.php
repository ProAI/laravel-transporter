<?php

namespace ProAI\Transporter;

use Illuminate\Support\Collection;

/** @phpstan-consistent-constructor */
class ArgumentBag extends Collection
{
    /**
     * Create a new argument bag.
     *
     * @param  mixed  $items
     * @return void
     */
    public function __construct(mixed $items = [])
    {
        foreach ($items as $key => $item) {
            if (is_array($item)) {
                $items[$key] = new static($item);
            }
        }

        $this->items = $this->getArrayableItems($items);
    }

    /**
     * Get all of the items in the collection.
     *
     * @return array<int|string, mixed>
     */
    public function all(): array
    {
        return array_map(function (mixed $item): mixed {
            if ($item instanceof static) {
                return $item->all();
            }

            return $item;
        }, $this->items);
    }
}
