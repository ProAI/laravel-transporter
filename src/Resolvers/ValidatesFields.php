<?php

namespace ProAI\Transporter\Resolvers;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\ValidationException;
use ProAI\Transporter\ArgumentBag;

trait ValidatesFields
{
    /**
     * Validate the given input with the given rules.
     *
     * @param  \ProAI\Transporter\ArgumentBag  $input
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return array
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(ArgumentBag $input, array $rules,
        array $messages = [], array $customAttributes = [])
    {
        return $this->getValidationFactory()->make(
            $input->all(), $rules, $messages, $customAttributes
        )->validate();
    }

    /**
     * Validate the given input with the given rules.
     *
     * @param  string  $errorBag
     * @param  \ProAI\Transporter\ArgumentBag  $input
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return array
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateWithBag($errorBag, ArgumentBag $input, array $rules,
        array $messages = [], array $customAttributes = [])
    {
        try {
            return $this->validate($input, $rules, $messages, $customAttributes);
        } catch (ValidationException $e) {
            $e->errorBag = $errorBag;

            throw $e;
        }
    }

    /**
     * Get a validation factory instance.
     *
     * @return \Illuminate\Contracts\Validation\Factory
     */
    protected function getValidationFactory()
    {
        return app(Factory::class);
    }
}
