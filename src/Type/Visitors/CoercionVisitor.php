<?php

namespace ProAI\Transporter\Type\Visitors;

use GraphQL\Type\Definition\ScalarType;
use ProAI\Transporter\ArgumentBag;
use ProAI\Transporter\Contracts\CoercesValue;
use ProAI\Transporter\Contracts\ParsesLiteral;
use ProAI\Transporter\Contracts\SerializesValue;

class CoercionVisitor
{
    /**
     * Visit scalar type.
     *
     * @param  \GraphQL\Type\Definition\ScalarType  $type
     * @param  \ProAI\Transporter\ArgumentBag  $args
     * @return void
     */
    public function visitScalar(ScalarType $type, ArgumentBag $args): void
    {
        $class = $this->determineClassName($args->get('class'));

        $instance = new $class;

        $this->setCoercion($type, $instance);
    }

    /**
     * Determine the class name.
     *
     * @param  string  $class
     * @return string
     */
    protected function determineClassName(string $class): string
    {
        return str_replace('/', '\\', $class);
    }

    /**
     * Set coercion instance on type.
     *
     * @param  \GraphQL\Type\Definition\ScalarType  $type
     * @param  \ProAI\Transporter\Contracts\SerializesValue  $instance
     * @return void
     */
    protected function setCoercion(ScalarType $type, SerializesValue $instance): void
    {
        $type->config['serialize'] = [$instance, 'serialize'];

        if ($instance instanceof CoercesValue) {
            $type->config['parseValue'] = [$instance, 'parseValue'];
        }

        if ($instance instanceof ParsesLiteral) {
            $type->config['parseLiteral'] = [$instance, 'parseLiteral'];
        }
    }
}
