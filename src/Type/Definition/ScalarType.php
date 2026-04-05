<?php

namespace ProAI\Transporter\Type\Definition;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\CustomScalarType as BaseScalarType;
use GraphQL\Utils\Utils;

class ScalarType extends BaseScalarType
{
    use Serializable;

    /**
     * Assert config is valid.
     *
     * @return void
     */
    public function assertValid(): void
    {
        Utils::assertValidName($this->name);

        if (! isset($this->config['serialize'])) {
            throw new InvariantViolation(
                sprintf('%s must provide "serialize" function. If this custom Scalar ', $this->name).
                'is also used as an input type, ensure "parseValue" and "parseLiteral" '.
                'functions are also provided.'
            );
        }
    }
}
