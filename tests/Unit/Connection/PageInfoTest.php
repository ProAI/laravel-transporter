<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use ProAI\Transporter\Connection\PageInfo;

it('reports no previous page on first page', function () {
    $paginator = new CursorPaginator(
        collect([createPageInfoModel(1), createPageInfoModel(2)]),
        2,
        null,
        ['parameters' => ['id']]
    );

    $pageInfo = new PageInfo($paginator);

    expect($pageInfo->hasPreviousPage())->toBeFalse();
});

it('reports previous page when cursor is provided', function () {
    $cursor = new Cursor(['id' => 3], true);

    $paginator = new CursorPaginator(
        collect([createPageInfoModel(3), createPageInfoModel(4)]),
        2,
        $cursor,
        ['parameters' => ['id']]
    );

    $pageInfo = new PageInfo($paginator);

    expect($pageInfo->hasPreviousPage())->toBeTrue();
});

it('reports next page when there are more items', function () {
    $paginator = new CursorPaginator(
        collect([createPageInfoModel(1), createPageInfoModel(2), createPageInfoModel(3)]),
        2,
        null,
        ['parameters' => ['id']]
    );

    $pageInfo = new PageInfo($paginator);

    expect($pageInfo->hasNextPage())->toBeTrue();
});

it('reports no next page when items fit in page', function () {
    $paginator = new CursorPaginator(
        collect([createPageInfoModel(1), createPageInfoModel(2)]),
        3,
        null,
        ['parameters' => ['id']]
    );

    $pageInfo = new PageInfo($paginator);

    expect($pageInfo->hasNextPage())->toBeFalse();
});

it('returns null start cursor for empty results', function () {
    $paginator = new CursorPaginator(
        collect(),
        10,
        null,
        ['parameters' => ['id']]
    );

    $pageInfo = new PageInfo($paginator);

    expect($pageInfo->startCursor())->toBeNull();
});

it('returns null end cursor for empty results', function () {
    $paginator = new CursorPaginator(
        collect(),
        10,
        null,
        ['parameters' => ['id']]
    );

    $pageInfo = new PageInfo($paginator);

    expect($pageInfo->endCursor())->toBeNull();
});

it('returns start cursor for non-empty results', function () {
    $paginator = new CursorPaginator(
        collect([createPageInfoModel(1), createPageInfoModel(2)]),
        2,
        null,
        ['parameters' => ['id']]
    );

    $pageInfo = new PageInfo($paginator);

    expect($pageInfo->startCursor())->toBeString();
    expect($pageInfo->startCursor())->not->toBeEmpty();
});

it('returns end cursor for non-empty results', function () {
    $paginator = new CursorPaginator(
        collect([createPageInfoModel(1), createPageInfoModel(2)]),
        2,
        null,
        ['parameters' => ['id']]
    );

    $pageInfo = new PageInfo($paginator);

    expect($pageInfo->endCursor())->toBeString();
    expect($pageInfo->endCursor())->not->toBeEmpty();
});

it('start and end cursors differ for multiple items', function () {
    $paginator = new CursorPaginator(
        collect([createPageInfoModel(1), createPageInfoModel(2)]),
        2,
        null,
        ['parameters' => ['id']]
    );

    $pageInfo = new PageInfo($paginator);

    expect($pageInfo->startCursor())->not->toBe($pageInfo->endCursor());
});

function createPageInfoModel(int $id): Model
{
    $model = new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;

        protected $table = 'test';
    };

    $model->id = $id;

    return $model;
}
