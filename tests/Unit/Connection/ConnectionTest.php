<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator;
use ProAI\Transporter\Connection\Connection;
use ProAI\Transporter\Connection\Edge;
use ProAI\Transporter\Connection\PageInfo;

it('creates a connection from a paginator', function () {
    $paginator = new CursorPaginator(
        collect([createConnectionModel(1), createConnectionModel(2)]),
        2,
        null,
        ['parameters' => ['id']]
    );

    $connection = new Connection($paginator);

    expect($connection->paginator)->toBe($paginator);
});

it('returns edges', function () {
    $paginator = new CursorPaginator(
        collect([createConnectionModel(1), createConnectionModel(2)]),
        2,
        null,
        ['parameters' => ['id']]
    );

    $connection = new Connection($paginator);
    $edges = $connection->edges();

    expect($edges)->toHaveCount(2);
    expect($edges[0])->toBeInstanceOf(Edge::class);
    expect($edges[1])->toBeInstanceOf(Edge::class);
});

it('returns nodes', function () {
    $model1 = createConnectionModel(1);
    $model2 = createConnectionModel(2);

    $paginator = new CursorPaginator(
        collect([$model1, $model2]),
        2,
        null,
        ['parameters' => ['id']]
    );

    $connection = new Connection($paginator);
    $nodes = $connection->nodes();

    expect($nodes)->toHaveCount(2);
});

it('returns page info', function () {
    $paginator = new CursorPaginator(
        collect([createConnectionModel(1)]),
        2,
        null,
        ['parameters' => ['id']]
    );

    $connection = new Connection($paginator);

    expect($connection->pageInfo())->toBeInstanceOf(PageInfo::class);
});

it('accesses extensions via magic get', function () {
    $paginator = new CursorPaginator(
        collect(),
        10,
        null,
        ['parameters' => ['id']]
    );

    $connection = new Connection($paginator, ['totalCount' => 42]);

    expect($connection->totalCount)->toBe(42);
});

it('returns null for missing extensions', function () {
    $paginator = new CursorPaginator(
        collect(),
        10,
        null,
        ['parameters' => ['id']]
    );

    $connection = new Connection($paginator);

    expect($connection->nonExistent)->toBeNull();
});

function createConnectionModel(int $id): Model
{
    $model = new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;

        protected $table = 'test_connections';
    };

    $model->id = $id;

    return $model;
}
