<?php

namespace ProAI\Transporter\Resolvers;

class CountResolver
{
    /**
     * Resolve field.
     *
     * @param  mixed  $source
     * @param  \ProAI\Transporter\ArgumentBag  $args
     * @param  \ProAI\Transporter\Context  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $info
     * @return mixed
     */
    public function __invoke(mixed $source, mixed $args, mixed $context, mixed $info): mixed
    {
        $relation = $this->getRelationName($info);

        return $context->relationLoader($source, $relation)->asyncCount();
    }

    /**
     * Get relation name from field name.
     *
     * @param  \GraphQL\Type\Definition\ResolveInfo  $info
     * @return string
     */
    protected function getRelationName(mixed $info): string
    {
        return preg_replace('/Count$/', '', $info->fieldName);
    }
}
