<?php

use Illuminate\Validation\ValidationException;
use ProAI\Transporter\ArgumentBag;
use ProAI\Transporter\Resolvers\Resolver;

it('validates input and returns validated data', function () {
    $resolver = new ConcreteTestResolver;
    $input = new ArgumentBag(['name' => 'John', 'email' => 'john@example.com']);

    $result = $resolver->validate($input, [
        'name' => 'required|string',
        'email' => 'required|email',
    ]);

    expect($result)->toBe(['name' => 'John', 'email' => 'john@example.com']);
});

it('throws ValidationException for invalid input', function () {
    $resolver = new ConcreteTestResolver;
    $input = new ArgumentBag(['name' => '']);

    $resolver->validate($input, ['name' => 'required']);
})->throws(ValidationException::class);

it('validates with custom messages', function () {
    $resolver = new ConcreteTestResolver;
    $input = new ArgumentBag(['name' => '']);

    try {
        $resolver->validate($input, ['name' => 'required'], ['name.required' => 'Name is mandatory']);
    } catch (ValidationException $e) {
        expect($e->errors()['name'][0])->toBe('Name is mandatory');

        return;
    }

    $this->fail('Expected ValidationException');
});

it('validates with error bag', function () {
    $resolver = new ConcreteTestResolver;
    $input = new ArgumentBag(['name' => '']);

    try {
        $resolver->validateWithBag('custom', $input, ['name' => 'required']);
    } catch (ValidationException $e) {
        expect($e->errorBag)->toBe('custom');

        return;
    }

    $this->fail('Expected ValidationException');
});

it('validates nested argument bag data', function () {
    $resolver = new ConcreteTestResolver;
    $input = new ArgumentBag([
        'user' => ['name' => 'John', 'age' => 25],
    ]);

    $result = $resolver->validate($input, [
        'user.name' => 'required|string',
        'user.age' => 'required|integer|min:18',
    ]);

    expect($result['user']['name'])->toBe('John');
    expect($result['user']['age'])->toBe(25);
});

class ConcreteTestResolver extends Resolver
{
    //
}
