<?php

namespace ProAI\Transporter\Type;

use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Type;
use ProAI\Transporter\Type\Definition\Directive;

class Directives
{
    /**
     * The directive instances cache.
     *
     * @var array
     */
    protected static array $cache;

    /**
     * Get built-in directives of laravel transporter.
     *
     * @return \GraphQL\Type\Definition\Directive[]
     */
    public static function getTransporterDirectives(): array
    {
        if (! isset(static::$cache)) {
            static::$cache = [
                static::buildCoercionDirective(),
                static::buildConnectionDirective(),
                static::buildCountDirective(),
                static::buildInternalDirective(),
                static::buildResolverDirective(),
                static::buildTypeResolverDirective(),
            ];
        }

        return static::$cache;
    }

    /**
     * Build coercion directive.
     *
     * @return \ProAI\Transporter\Type\Definition\Directive
     */
    protected static function buildCoercionDirective(): Directive
    {
        $directive = new Directive([
            'name' => 'coercion',
            'locations' => [
                DirectiveLocation::SCALAR,
            ],
            'args' => [
                'class' => [
                    'type' => Type::nonNull(Type::string()),
                ],
            ],
        ]);

        $directive->visitor(Visitors\CoercionVisitor::class);

        return $directive;
    }

    /**
     * Build connection directive.
     *
     * @return \ProAI\Transporter\Type\Definition\Directive
     */
    protected static function buildConnectionDirective(): Directive
    {
        $directive = new Directive([
            'name' => 'connection',
            'locations' => [
                DirectiveLocation::FIELD_DEFINITION,
            ],
        ]);

        $directive->visitor(Visitors\ConnectionVisitor::class);

        return $directive;
    }

    /**
     * Build count directive.
     *
     * @return \ProAI\Transporter\Type\Definition\Directive
     */
    protected static function buildCountDirective(): Directive
    {
        $directive = new Directive([
            'name' => 'count',
            'locations' => [
                DirectiveLocation::FIELD_DEFINITION,
            ],
        ]);

        $directive->visitor(Visitors\CountVisitor::class);

        return $directive;
    }

    /**
     * Build internal directive.
     *
     * @return \ProAI\Transporter\Type\Definition\Directive
     */
    protected static function buildInternalDirective(): Directive
    {
        $directive = new Directive([
            'name' => 'values',
            'locations' => [
                DirectiveLocation::ENUM,
            ],
            'args' => [
                'class' => [
                    'type' => Type::nonNull(Type::string()),
                ],
            ],
        ]);

        $directive->visitor(Visitors\InternalVisitor::class);

        return $directive;
    }

    /**
     * Build resolver directive.
     *
     * @return \ProAI\Transporter\Type\Definition\Directive
     */
    protected static function buildResolverDirective(): Directive
    {
        $directive = new Directive([
            'name' => 'resolver',
            'locations' => [
                DirectiveLocation::FIELD_DEFINITION,
            ],
            'args' => [
                'class' => [
                    'type' => Type::nonNull(Type::string()),
                ],
            ],
        ]);

        $directive->visitor(Visitors\ResolverVisitor::class);

        return $directive;
    }

    /**
     * Build type resolver directive.
     *
     * @return \ProAI\Transporter\Type\Definition\Directive
     */
    protected static function buildTypeResolverDirective(): Directive
    {
        $directive = new Directive([
            'name' => 'typeResolver',
            'locations' => [
                DirectiveLocation::IFACE,
                DirectiveLocation::UNION,
            ],
            'args' => [
                'class' => [
                    'type' => Type::nonNull(Type::string()),
                ],
            ],
        ]);

        $directive->visitor(Visitors\TypeResolverVisitor::class);

        return $directive;
    }
}
