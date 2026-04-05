<?php

use ProAI\Transporter\Schema\Source;

it('throws when SDL file does not exist', function () {
    new Source('/nonexistent/path', ['schema']);
})->throws(Exception::class);

it('finds gql files', function () {
    $basePath = createTempSchemaDir();
    file_put_contents($basePath.'/schema.gql', 'type Query { hello: String }');

    $source = new Source($basePath, ['schema']);

    expect($source->getSDLPath('schema'))->toBe($basePath.'/schema.gql');
    expect($source->getPHPPath('schema'))->toBeNull();

    cleanupTempDir($basePath);
});

it('finds graphql files', function () {
    $basePath = createTempSchemaDir();
    file_put_contents($basePath.'/schema.graphql', 'type Query { hello: String }');

    $source = new Source($basePath, ['schema']);

    expect($source->getSDLPath('schema'))->toBe($basePath.'/schema.graphql');

    cleanupTempDir($basePath);
});

it('finds optional PHP files', function () {
    $basePath = createTempSchemaDir();
    file_put_contents($basePath.'/schema.gql', 'type Query { hello: String }');
    file_put_contents($basePath.'/schema.php', '<?php return [];');

    $source = new Source($basePath, ['schema']);

    expect($source->getPHPPath('schema'))->toBe($basePath.'/schema.php');

    cleanupTempDir($basePath);
});

it('returns all paths', function () {
    $basePath = createTempSchemaDir();
    file_put_contents($basePath.'/schema.gql', 'type Query { hello: String }');
    file_put_contents($basePath.'/schema.php', '<?php return [];');

    $source = new Source($basePath, ['schema']);
    $paths = $source->getPaths();

    expect($paths)->toContain($basePath.'/schema.gql');
    expect($paths)->toContain($basePath.'/schema.php');

    cleanupTempDir($basePath);
});

it('generates a hash', function () {
    $basePath = createTempSchemaDir();
    file_put_contents($basePath.'/schema.gql', 'type Query { hello: String }');

    $source = new Source($basePath, ['schema']);

    expect($source->getHash())->toBeString();
    expect(strlen($source->getHash()))->toBe(40); // SHA1 hash length

    cleanupTempDir($basePath);
});

it('generates consistent hash for same input', function () {
    $basePath = createTempSchemaDir();
    file_put_contents($basePath.'/schema.gql', 'type Query { hello: String }');

    $source1 = new Source($basePath, ['schema']);
    $source2 = new Source($basePath, ['schema']);

    expect($source1->getHash())->toBe($source2->getHash());

    cleanupTempDir($basePath);
});

it('converts dot notation to directory paths', function () {
    $basePath = createTempSchemaDir();
    mkdir($basePath.'/sub', 0777, true);
    file_put_contents($basePath.'/sub/schema.gql', 'type Query { hello: String }');

    $source = new Source($basePath, ['sub.schema']);

    expect($source->getSDLPath('sub.schema'))->toBe($basePath.'/sub/schema.gql');

    cleanupTempDir($basePath);
});

function createTempSchemaDir(): string
{
    $dir = sys_get_temp_dir().'/transporter_test_'.uniqid();
    mkdir($dir, 0777, true);

    return $dir;
}

function cleanupTempDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($dir);
}
