<?php

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use ProAI\Transporter\ArgumentBag;
use ProAI\Transporter\Context;
use ProAI\Transporter\Middleware\AuthorizeResult;

it('passes through non-model results', function () {
    $gate = Mockery::mock(Gate::class);
    $middleware = new AuthorizeResult($gate);
    $context = new Context(app());

    $request = ['source', new ArgumentBag, $context, null];

    $result = $middleware->handle($request, function ($request) {
        return 'string-result';
    });

    expect($result)->toBe('string-result');
});

it('passes through null results', function () {
    $gate = Mockery::mock(Gate::class);
    $middleware = new AuthorizeResult($gate);
    $context = new Context(app());

    $request = ['source', new ArgumentBag, $context, null];

    $result = $middleware->handle($request, function ($request) {
        return null;
    });

    expect($result)->toBeNull();
});

it('passes through array results', function () {
    $gate = Mockery::mock(Gate::class);
    $middleware = new AuthorizeResult($gate);
    $context = new Context(app());

    $request = ['source', new ArgumentBag, $context, null];

    $result = $middleware->handle($request, function ($request) {
        return ['data' => 'value'];
    });

    expect($result)->toBe(['data' => 'value']);
});

it('makes response for model with policy', function () {
    $model = new class extends Model
    {
        protected $guarded = [];
    };
    $model->id = 1;

    $gate = Mockery::mock(Gate::class);
    $gate->shouldReceive('getPolicyFor')->with($model)->andReturn(new stdClass);
    $gate->shouldReceive('inspect')->with('view', $model)->andReturn(Response::allow());

    $middleware = new AuthorizeResult($gate);
    $context = new Context(app());

    $response = $middleware->makeResponse($context, $model);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->allowed())->toBeTrue();
});

it('returns null when no policy exists', function () {
    $model = new class extends Model
    {
        protected $guarded = [];
    };
    $model->id = 1;

    $gate = Mockery::mock(Gate::class);
    $gate->shouldReceive('getPolicyFor')->with($model)->andReturn(null);

    $middleware = new AuthorizeResult($gate);
    $context = new Context(app());

    $response = $middleware->makeResponse($context, $model);

    expect($response)->toBeNull();
});

it('caches policy responses for same model', function () {
    $model = new class extends Model
    {
        protected $guarded = [];
    };
    $model->id = 1;

    $gate = Mockery::mock(Gate::class);
    $gate->shouldReceive('getPolicyFor')->once()->with($model)->andReturn(new stdClass);
    $gate->shouldReceive('inspect')->once()->with('view', $model)->andReturn(Response::allow());

    $middleware = new AuthorizeResult($gate);
    $context = new Context(app());

    $response1 = $middleware->makeResponse($context, $model);
    $response2 = $middleware->makeResponse($context, $model);

    expect($response1)->toBe($response2);
});
