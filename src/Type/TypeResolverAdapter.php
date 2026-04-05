<?php

namespace ProAI\Transporter\Type;

use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\ResolveInfo;
use ProAI\Transporter\Context;

/** @phpstan-consistent-constructor */
class TypeResolverAdapter
{
    /**
     * The resolver callback.
     *
     * @var string|\Closure
     */
    protected string|\Closure $callback;

    /**
     * Create a new type resolver adapter instance.
     *
     * @param  string|\Closure  $callback
     * @return void
     */
    public function __construct(string|\Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Create a type resolver adapter instance for an abstract type.
     *
     * @param  \GraphQL\Type\Definition\InterfaceType|\GraphQL\Type\Definition\UnionType  $type
     * @param  string|\Closure  $callback
     * @return void
     */
    public static function forType(AbstractType $type, string|\Closure $callback): void
    {
        $resolveTypeFn = new static($callback);

        $type->config['resolveType'] = $resolveTypeFn;
    }

    /**
     * Execute resolver.
     *
     * @param  mixed  $source
     * @param  \ProAI\Transporter\Context  $context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $info
     * @return mixed
     */
    public function __invoke(mixed $source, Context $context, ResolveInfo $info): mixed
    {
        $parameters = [$source, $context, $info];

        return $context->callTypeResolver($this->callback, $parameters);
    }
}
