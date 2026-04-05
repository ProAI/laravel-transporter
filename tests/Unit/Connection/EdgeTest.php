<?php

use Illuminate\Pagination\Cursor;
use ProAI\Transporter\Connection\Edge;

it('creates an edge with node and cursor', function () {
    $node = new stdClass;
    $node->name = 'Test';

    $cursor = new Cursor(['id' => 1]);
    $edge = new Edge($node, $cursor);

    expect($edge->node)->toBe($node);
    expect($edge->cursor())->toBe($cursor->encode());
});

it('returns encoded cursor string', function () {
    $cursor = new Cursor(['id' => 42]);
    $edge = new Edge('node-value', $cursor);

    $encoded = $edge->cursor();

    expect($encoded)->toBeString();
    expect($encoded)->not->toBeEmpty();
});

it('accesses extensions via magic get', function () {
    $cursor = new Cursor(['id' => 1]);
    $edge = new Edge('node', $cursor, ['role' => 'admin']);

    expect($edge->role)->toBe('admin');
});

it('returns null for missing extensions', function () {
    $cursor = new Cursor(['id' => 1]);
    $edge = new Edge('node', $cursor);

    expect($edge->nonExistent)->toBeNull();
});
