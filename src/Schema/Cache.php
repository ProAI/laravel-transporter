<?php

namespace ProAI\Transporter\Schema;

use GraphQL\Type\Schema;
use Illuminate\Filesystem\Filesystem;

class Cache
{
    use Concerns\SerializesSchema;

    /**
     * The Filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected Filesystem $files;

    /**
     * The path to the schema cache directory.
     *
     * @var string
     */
    protected string $cachePath;

    /**
     * Create a new schema cache instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  string  $cachePath
     * @return void
     */
    public function __construct(Filesystem $files, string $cachePath)
    {
        $this->files = $files;

        $this->cachePath = $cachePath;
    }

    /**
     * Get a cached schema.
     *
     * @param  \ProAI\Transporter\Schema\Source  $source
     * @return \GraphQL\Type\Schema
     */
    public function get(Source $source): Schema
    {
        $cached = $this->getCachedSchemaPath($source);

        return require $cached;
    }

    /**
     * Put a schema into cache.
     *
     * @param  \ProAI\Transporter\Schema\Source  $source
     * @param  \GraphQL\Type\Schema  $schema
     * @return void
     */
    public function put(Source $source, Schema $schema): void
    {
        $cached = $this->getCachedSchemaPath($source);
        $stub = $this->files->get(__DIR__.'/../../stubs/schema.stub');

        $this->prepareForSerialization($schema);

        $contents = str_replace('{{schema}}', base64_encode(\Opis\Closure\serialize($schema)), $stub);

        $this->files->put($cached, $contents);
    }

    /**
     * Delete a schema from cache.
     *
     * @param  \ProAI\Transporter\Schema\Source  $source
     * @param  \GraphQL\Type\Schema  $schema
     * @return void
     */
    public function delete(Source $source, Schema $schema): void
    {
        $cached = $this->getCachedSchemaPath($source);

        $this->files->delete($cached);
    }

    /**
     * Flush the schema cache.
     *
     * @return void
     */
    public function flush(): void
    {
        foreach ($this->files->glob("{$this->cachePath}/*") as $cachedSchema) {
            $this->files->delete($cachedSchema);
        }
    }

    /**
     * Determine if the schema at the given path is expired.
     *
     * @param  \ProAI\Transporter\Schema\Source  $source
     * @return bool
     */
    public function isExpired(Source $source): bool
    {
        $cached = $this->getCachedSchemaPath($source);

        if (! $this->files->exists($cached)) {
            return true;
        }

        $cachedLastModified = $this->files->lastModified($cached);
        foreach ($source->getPaths() as $path) {
            if ($this->files->lastModified($path) >= $cachedLastModified) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the path to the cached version of a schema.
     *
     * @param  \ProAI\Transporter\Schema\Source  $source
     * @return string
     */
    protected function getCachedSchemaPath(Source $source): string
    {
        return $this->cachePath.'/'.$source->getHash().'.php';
    }
}
