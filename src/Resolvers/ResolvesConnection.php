<?php

namespace ProAI\Transporter\Resolvers;

use Exception;
use ProAI\Transporter\Connection\Connection;

trait ResolvesConnection
{
    /**
     * Resolve connection.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $source
     * @param  string  $relation
     * @param  \ProAI\Transporter\ArgumentBag  $args
     * @param  \GraphQL\Type\Definition\ResolveInfo  $info
     * @param  callable|null  $constraints
     * @return \ProAI\Transporter\Connection\Connection
     */
    public function resolveConnection($source, $relation, $args, $info, $constraints = null)
    {
        $query = $source->{$relation}();

        if ($constraints) {
            $constraints($query);
        }

        if ($args->get('first')) {
            return Connection::make(
                $query,
                $args->get('first'),
                $args->get('after'),
                true
            );
        }

        if ($args->get('last')) {
            // TODO: Implement support for before=null
            // Currently it is not possible to reverse the selection without
            // defining a cursor. For now throw an exception.
            if (! $args->get('before')) {
                throw new Exception('It is currently not supported to use `last` without `before`.');
            }

            return Connection::make(
                $query,
                $args->get('last'),
                $args->get('before'),
                false
            );
        }

        field_error(
            'You must provide a `first` or `last` value to properly paginate the `'.$info->fieldName.'` connection.',
            'MISSING_PAGINATION_BOUNDARIES'
        );
    }
}
