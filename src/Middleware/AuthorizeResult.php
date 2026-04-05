<?php

namespace ProAI\Transporter\Middleware;

use Closure;
use Exception;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use ProAI\Transporter\Context;
use ProAI\Transporter\Contracts\HasParent;
use ProAI\Transporter\Shield;
use ProAI\Transporter\Transporter;

class AuthorizeResult
{
    /**
     * The key of the attribute.
     *
     * @var \Illuminate\Auth\Access\Gate
     */
    protected Gate $gate;

    /**
     * The cached policy responses.
     *
     * @var array
     */
    protected array $cache = [];

    /**
     * Create a new access manager instance.
     *
     * @param  \Illuminate\Auth\Access\Gate  $gate
     * @return void
     */
    public function __construct(Gate $gate)
    {
        $this->gate = $gate;
    }

    /**
     * Handle authorize middleware.
     *
     * @param  array  $request
     * @return mixed
     */
    public function handle(array $request, Closure $next): mixed
    {
        $context = $request[2];

        $result = $next($request);

        if (is_object($result) && method_exists($result, 'then')) {
            return $result->then(function (mixed $result) use ($context) {
                $this->transformResult($context, $result);

                return $result;
            });
        }

        $this->transformResult($context, $result);

        return $result;
    }

    /**
     * Transform resolved models.
     *
     * @param  \ProAI\Transporter\Context  $context
     * @param  mixed  $result
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function transformResult(Context $context, mixed $result): void
    {
        if ($result instanceof Model) {
            $result = Collection::wrap($result);
        }

        if (! $result instanceof Collection) {
            return;
        }

        Shield::disableFor(function () use ($result, $context) {
            $result->each(function (mixed $item) use ($context) {
                $this->inspectItem($context, $item);
            });
        });
    }

    /**
     * Inspect resolved item.
     *
     * @param  \ProAI\Transporter\Context  $context
     * @param  \Illuminate\Database\Eloquent\Model  $item
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Exception
     */
    protected function inspectItem(Context $context, Model $item): void
    {
        $response = $this->makeResponse($context, $item);

        if (is_null($response)) {
            $this->authorizeParent($context, $item);

            return;
        }

        // Allow authorization if access is restricted to some attributes,
        // because access to each attribute will be checked in the child
        // resolvers.
        if ($response instanceof Shield) {
            if (! method_exists($item, 'setShield')) {
                throw new Exception('Method "setShield" not found on model '.get_class($item).'. Did you implement the ShieldsAttributes trait?');
            }

            $item->setShield($response);

            return;
        }

        $response->authorize();
    }

    /**
     * Authorize parent of resolved item.
     *
     * @param  \ProAI\Transporter\Context  $context
     * @param  \Illuminate\Database\Eloquent\Model  $item
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function authorizeParent(Context $context, Model $item): void
    {
        // Return if item has no parent.
        if (! $item instanceof HasParent) {
            if (Transporter::$enforcedPolicies) {
                throw new Exception('No policy found for model "'.get_class($item).'".');
            }

            return;
        }

        $relation = $item->parent();
        $relationName = $relation->getRelationName();

        $parent = $item->getRelationValue($relationName);

        $response = $this->makeResponse($context, $parent);

        if (is_null($response)) {
            $this->authorizeParent($context, $parent);

            return;
        }

        if ($response instanceof Shield) {
            $response->authorizeForRelation(
                $relation->getInverseRelationName()
            );
        } else {
            $response->authorize();
        }
    }

    /**
     * Get authorization response for a model.
     *
     * @param  \ProAI\Transporter\Context  $context
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Auth\Access\Response|null
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function makeResponse(Context $context, Model $model): Response|Shield|null
    {
        $key = $this->getKey($context, $model);

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if ($this->gate->getPolicyFor($model)) {
            $response = $this->gate->inspect('view', $model);
        } else {
            $response = null;
        }

        return $this->cache[$key] = $response;
    }

    /**
     * Get the cache key for the response.
     *
     * @param  \ProAI\Transporter\Context  $context
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return string
     */
    protected function getKey(Context $context, Model $model): string
    {
        $hash = spl_object_hash($context);

        return $hash.'.'.get_class($model).'.'.$model->getKey();
    }
}
