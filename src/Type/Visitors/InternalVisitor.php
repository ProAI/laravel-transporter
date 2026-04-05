<?php

namespace ProAI\Transporter\Type\Visitors;

use Exception;
use GraphQL\Type\Definition\EnumType;
use ProAI\Transporter\ArgumentBag;

class InternalVisitor
{
    /**
     * Visit enum type.
     *
     * @param  \GraphQL\Type\Definition\EnumType  $type
     * @param  \ProAI\Transporter\ArgumentBag  $args
     * @return void
     */
    public function visitEnum(EnumType $type, ArgumentBag $args): void
    {
        if (! isset($type->config['values'])) {
            return;
        }

        $class = $this->determineClassName($args->get('class'));

        if (! enum_exists($class)) {
            throw new Exception('Class "'.$class.'" is not an enum.');
        }

        // Add values from class as internal values.
        $this->overwriteEnumValues($type, $class);
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
     * Overwrite enum values using given enum class.
     *
     * @param  \GraphQL\Type\Definition\EnumType  $type
     * @param  string  $class
     * @return void
     */
    protected function overwriteEnumValues(EnumType $type, string $class): void
    {
        $values = [];

        foreach ($type->config['values'] as $name => $def) {
            $values[$name] = array_merge($def, [
                'value' => constant($class.'::'.$name),
            ]);
        }

        $type->config['values'] = $values;
    }
}
