<?php

namespace ProAI\Transporter\Type\Definition;

use GraphQL\Type\Definition\InterfaceType as BaseInterfaceType;
use GraphQL\Type\Definition\ResolveInfo;

class InterfaceType extends BaseInterfaceType
{
    use Serializable;

    /**
     * Resolves concrete ObjectType for given object value
     *
     * @param  object  $value
     * @param  mixed  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $info
     * @return mixed
     */
    public function resolveType(mixed $value, mixed $context, ResolveInfo $info): mixed
    {
        if (isset($this->config['resolveType'])) {
            return parent::resolveType($value, $context, $info);
        }

        return class_basename($value);
    }
}
