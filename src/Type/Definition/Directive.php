<?php

namespace ProAI\Transporter\Type\Definition;

use Exception;
use GraphQL\Type\Definition\Directive as BaseDirective;
use Illuminate\Support\Str;
use ProAI\Transporter\ArgumentBag;

class Directive extends BaseDirective
{
    use Serializable;

    /**
     * The visitor instance.
     *
     * @var object
     */
    protected object $visitor;

    /**
     * Set the visitor instance.
     *
     * @param  string  $class
     * @return void
     *
     * @throws \Exception
     */
    public function visitor(string $class): void
    {
        if (! $this->validateVisitor($class)) {
            throw new Exception('Visitor class "'.$class.'" is not complient with definition of directive '.$this->name.'.');
        }

        $this->visitor = new $class;
    }

    /**
     * Validate the visitor class.
     *
     * @param  string  $class
     * @return bool
     */
    protected function validateVisitor(string $class): bool
    {
        foreach ($this->locations as $location) {
            $method = 'visit'.Str::studly(Str::lower($location));

            if (! method_exists($class, $method)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Visit directive location.
     *
     * @param  string  $location
     * @param  object  $item
     * @param  array  $args
     * @return void
     */
    public function visit(string $location, object $item, array $args): void
    {
        if (! in_array($location, $this->locations)) {
            return;
        }

        $method = 'visit'.Str::studly(strtolower($location));

        // Handle locations
        if (method_exists($this->visitor, $method)) {
            $this->visitor->{$method}($item, new ArgumentBag($args));
        }
    }
}
