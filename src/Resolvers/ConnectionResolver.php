<?php

namespace ProAI\Transporter\Resolvers;

class ConnectionResolver
{
    use ResolvesConnection;

    /**
     * Resolve field.
     *
     * @param  mixed  $source
     * @param  \ProAI\Transporter\ArgumentBag  $args
     * @param  mixed  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $info
     * @return mixed
     */
    public function __invoke(mixed $source, mixed $args, mixed $context, mixed $info): mixed
    {
        $relation = $this->getRelationName($info);

        return $this->resolveConnection($source, $relation, $args, $info);
    }

    /**
     * Get relation name from field name.
     *
     * @param  \GraphQL\Type\Definition\ResolveInfo  $info
     * @return string
     */
    protected function getRelationName(mixed $info): string
    {
        return preg_replace('/Connection$/', '', $info->fieldName);
    }
}
