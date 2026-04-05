<?php

namespace ProAI\Transporter\Connection;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Cursor as BaseCursor;
use Illuminate\Pagination\CursorPaginator;
use ProAI\Transporter\Contracts\HasClientKey;

/** @phpstan-consistent-constructor */
class Cursor extends BaseCursor
{
    /**
     * The cursor client key parser callback.
     *
     * @var \Closure
     */
    protected static ?Closure $clientKeyParser = null;

    /**
     * The cursor client key serializer callback.
     *
     * @var \Closure
     */
    protected static ?Closure $clientKeySerializer = null;

    /**
     * Get a cursor instance from the encoded string representation.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $query
     * @param  string|null  $encodedString
     * @param  bool  $pointsToNextItems
     * @return static|null
     */
    public static function fromInput(mixed $query, ?string $encodedString, bool $pointsToNextItems): ?static
    {
        $cursor = BaseCursor::fromEncoded($encodedString);

        if (! $cursor) {
            return null;
        }

        $model = $query->getRelated();

        if (! $model instanceof HasClientKey) {
            return new static($cursor->parameters, $pointsToNextItems);
        }

        $key = $model->getQualifiedKeyName();

        if (! array_key_exists($key, $cursor->parameters)) {
            return new static($cursor->parameters, $pointsToNextItems);
        }

        $value = $cursor->parameters[$key];

        if (isset(static::$clientKeyParser)) {
            $value = call_user_func(static::$clientKeyParser, $value);
        }

        // Add subquery that transforms the model client key to the model key.
        $subQuery = function (mixed $sub) use ($model, $key, $value) {
            $sub->select($key)
                ->from($model->getTable())
                ->where($model->getClientKeyName(), $value)
                ->limit(1);
        };

        $parameters = [...$cursor->parameters, $key => $subQuery];

        return new static($parameters, $pointsToNextItems);
    }

    /**
     * Get a cursor instance from a base cursor.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $item
     * @param  \Illuminate\Pagination\CursorPaginator  $paginator
     * @return static
     */
    public static function forItem(Model $item, CursorPaginator $paginator): static
    {
        $cursor = $paginator->getCursorForItem($item);

        if (! $item instanceof HasClientKey) {
            return new static($cursor->parameters);
        }

        $key = $item->getQualifiedKeyName();

        if (! array_key_exists($key, $cursor->parameters)) {
            return new static($cursor->parameters);
        }

        $value = $item->getClientKey();

        if (isset(static::$clientKeySerializer)) {
            $value = call_user_func(static::$clientKeySerializer, $value);
        }

        $parameters = [...$cursor->parameters, $key => $value];

        return new static($parameters);
    }

    /**
     * Set the cursor client key parser callback.
     *
     * @param  \Closure  $parser
     * @return void
     */
    public static function setClientKeyParser(Closure $parser): void
    {
        static::$clientKeyParser = $parser;
    }

    /**
     * Set the cursor client key serializer callback.
     *
     * @param  \Closure  $serializer
     * @return void
     */
    public static function setClientKeySerializer(Closure $serializer): void
    {
        static::$clientKeySerializer = $serializer;
    }
}
