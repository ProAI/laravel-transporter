<?php

use Illuminate\Filesystem\Filesystem;
use ProAI\Transporter\Schema\Cache;
use ProAI\Transporter\Schema\Source;

beforeEach(function () {
    $this->cachePath = sys_get_temp_dir().'/transporter_cache_test_'.uniqid();
    mkdir($this->cachePath, 0777, true);

    $this->files = new Filesystem;
    $this->cache = new Cache($this->files, $this->cachePath);
});

afterEach(function () {
    // Clean up
    $files = glob($this->cachePath.'/*');
    foreach ($files as $file) {
        unlink($file);
    }
    if (is_dir($this->cachePath)) {
        rmdir($this->cachePath);
    }
});

it('reports expired when cache file does not exist', function () {
    $basePath = createCacheTestSchemaDir();
    file_put_contents($basePath.'/schema.gql', 'type Query { hello: String }');

    $source = new Source($basePath, ['schema']);

    expect($this->cache->isExpired($source))->toBeTrue();

    cleanupCacheTestDir($basePath);
});

it('flushes all cached schemas', function () {
    // Create some fake cache files
    file_put_contents($this->cachePath.'/test1.php', '<?php return null;');
    file_put_contents($this->cachePath.'/test2.php', '<?php return null;');

    expect(count(glob($this->cachePath.'/*')))->toBe(2);

    $this->cache->flush();

    expect(count(glob($this->cachePath.'/*')))->toBe(0);
});

function createCacheTestSchemaDir(): string
{
    $dir = sys_get_temp_dir().'/transporter_cache_schema_'.uniqid();
    mkdir($dir, 0777, true);

    return $dir;
}

function cleanupCacheTestDir(string $dir): void
{
    $files = glob($dir.'/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if (is_dir($dir)) {
        rmdir($dir);
    }
}
