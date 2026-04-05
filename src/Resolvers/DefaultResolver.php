<?php

namespace ProAI\Transporter\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ProAI\Transporter\Contracts\HasClientKey;
use ProAI\Transporter\Transporter;

class DefaultResolver
{
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
        // Resolve identifier.
        if ($info->fieldName === Transporter::$identifierField) {
            return $this->getIdentifier($source);
        }

        $attributeKey = $this->getAttributeKeyName($info->fieldName);

        // Resolve plain object attribute.
        if (! $source instanceof Model) {
            return method_exists($source, $info->fieldName)
                ? $source->{$info->fieldName}()
                : $source->{$attributeKey};
        }

        // Resolve attribute of model source.
        if ($this->hasAttribute($source, $attributeKey)) {
            return $source->getAttribute($attributeKey);
        }

        // Resolve relation of model source.
        return $context->relationLoader($source, $info->fieldName)->asyncLoad();
    }

    /**
     * Get the identifier value of the source.
     *
     * @param  mixed  $source
     * @return mixed
     */
    protected function getIdentifier(mixed $source): mixed
    {
        if ($source instanceof HasClientKey) {
            return $source->getClientKey();
        }

        $key = $this->getAttributeKeyName(Transporter::$identifierField);

        return $source->{$key};
    }

    /**
     * Get the attribute key for the model.
     *
     * @param  string  $key
     * @return string
     */
    protected function getAttributeKeyName(string $key): string
    {
        if (! Model::$snakeAttributes) {
            return $key;
        }

        return Str::snake($key);
    }

    /**
     * Determine if key is an attribute of the model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $source
     * @param  string  $key
     * @return bool
     */
    protected function hasAttribute(Model $source, string $key): bool
    {
        return array_key_exists($key, $source->getAttributes()) ||
               array_key_exists($key, $source->getCasts()) ||
               $source->hasGetMutator($key) ||
               $source->hasCast($key);
    }
}
