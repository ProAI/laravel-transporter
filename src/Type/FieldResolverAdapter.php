<?php

namespace ProAI\Transporter\Type;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use ProAI\Transporter\ArgumentBag;
use ProAI\Transporter\Context;

/** @phpstan-consistent-constructor */
class FieldResolverAdapter
{
    /**
     * The resolver callback.
     *
     * @var string|array|\Closure
     */
    protected string|array|\Closure $callback;

    /**
     * Create a new resolver adapter instance.
     *
     * @param  string|array|\Closure  $callback
     * @return void
     */
    public function __construct(string|array|\Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Create a resolver adapter instance for a field.
     *
     * @param  \GraphQL\Type\Definition\FieldDefinition  $field
     * @param  string|array|\Closure  $callback
     * @return void
     */
    public static function forField(FieldDefinition $field, string|array|\Closure $callback): void
    {
        $resolveFn = new static($callback);

        $field->resolveFn = $field->config['resolve'] = $resolveFn;
    }

    /**
     * Execute resolver.
     *
     * @param  mixed  $source
     * @param  array  $args
     * @param  \ProAI\Transporter\Context  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $info
     * @return mixed
     */
    public function __invoke(mixed $source, array $args, Context $context, ResolveInfo $info): mixed
    {
        $args = new ArgumentBag($args);

        $parameters = [$source, $args, $context, $info];

        return $context->callResolver($this->callback, $parameters);
    }
}
