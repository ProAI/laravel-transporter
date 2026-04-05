<?php

namespace ProAI\Transporter;

use GraphQL\Error\Error;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class ErrorHandler
{
    /**
     * The exception handler instance.
     *
     * @var \Illuminate\Contracts\Debug\ExceptionHandler
     */
    protected ExceptionHandler $exceptionHandler;

    /**
     * Create a new error handler instance.
     *
     * @param  \Illuminate\Contracts\Debug\ExceptionHandler  $exceptionHandler
     * @return void
     */
    public function __construct(ExceptionHandler $exceptionHandler)
    {
        $this->exceptionHandler = $exceptionHandler;
    }

    /**
     * Invoke error handler.
     *
     * @param  \GraphQL\Error\Error[]  $errors
     * @param  callable  $formatter
     * @return array
     */
    public function __invoke(array $errors, callable $formatter)
    {
        foreach ($errors as $key => $error) {
            if ($safeError = $this->handleError($error)) {
                $errors[$key] = $safeError;
            }
        }

        return array_map($formatter, $errors);
    }

    /**
     * Handle error.
     *
     * @param  \GraphQL\Error\Error  $error
     * @return \GraphQL\Error\Error
     */
    protected function handleError(Error $error): ?Error
    {
        $e = $error->getPrevious();

        // Only report not client safe errors.
        // Since $error is checking if the previous error is client safe, we
        // can simply use $error->isClientSafe() here for all cases.
        if ($e && ! $error->isClientSafe()) {
            $this->exceptionHandler->report($e);
        }

        if ($e instanceof FieldException) {
            $extensions = ['code' => $e->getCode()];

            if ($e->properties) {
                $extensions['exception'] = $e->properties;
            }

            return $this->formatError(
                $error,
                $e->getMessage(),
                $extensions
            );
        }

        if ($e instanceof AuthenticationException) {
            return $this->formatError(
                $error,
                $e->getMessage() ?: 'Unauthenticated.',
                ['code' => 'UNAUTHENTICATED']
            );
        }

        if ($e instanceof ModelNotFoundException) {
            $message = config('app.debug')
                ? $e->getMessage()
                : 'Model not found.';

            return $this->formatError(
                $error,
                $message,
                ['code' => 'NOT_FOUND']
            );
        }

        if ($e instanceof AuthorizationException) {
            $message = config('app.debug') && $e->getMessage()
                ? $e->getMessage()
                : 'This action is unauthorized.';

            return $this->formatError(
                $error,
                $message,
                ['code' => 'FORBIDDEN']
            );
        }

        if ($e instanceof ValidationException) {
            $extensions = [
                'code' => 'BAD_USER_INPUT',
                'exception' => ['validationErrors' => $e->errors()],
            ];

            return $this->formatError(
                $error,
                $e->getMessage(),
                $extensions
            );
        }

        return null;
    }

    /**
     * Format GraphQL error.
     *
     * @param  \GraphQL\Error\Error  $error
     * @param  string  $message
     * @param  array  $extensions
     * @return \GraphQL\Error\Error
     */
    protected function formatError(Error $error, string $message, array $extensions = []): Error
    {
        return new Error(
            $message,
            $error->getNodes(),
            $error->getSource(),
            $error->getPositions(),
            $error->getPath(),
            null,
            $extensions
        );
    }
}
