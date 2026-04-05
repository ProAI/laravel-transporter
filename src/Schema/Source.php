<?php

namespace ProAI\Transporter\Schema;

use Exception;
use GraphQL\Type\Schema;

class Source
{
    /**
     * The path to the graphql schemas directory
     *
     * @var string
     */
    protected string $basePath;

    /**
     * The hash of the source
     *
     * @var string
     */
    protected string $hash;

    /**
     * All paths to sdl files (ending is either *.gql or *.graphql)
     *
     * @var array
     */
    protected array $sdlPaths = [];

    /**
     * All paths to php files
     *
     * @var array
     */
    protected array $phpPaths = [];

    /**
     * Create a new schema path bag instance.
     *
     * @param  string  $basePath
     * @param  array  $items
     * @return void
     */
    public function __construct(string $basePath, array $items)
    {
        $this->basePath = $basePath;

        foreach ($items as $item) {
            if ($item instanceof Schema) {
                continue;
            }

            $this->evaluatePaths($item);
        }

        $this->makeHash($items);
    }

    /**
     * Evaluate if *.gql/*.graphql (and optionally *.php) file of schema exist
     *
     * @param  string  $key
     * @return void
     *
     * @throws \Exception
     */
    protected function evaluatePaths(string $key): void
    {
        $fileName = str_replace('.', '/', $key);
        $path = $this->basePath.'/'.$fileName;

        // add *.gql or *.graphql file of schema
        if (file_exists($path.'.gql')) {
            $this->sdlPaths[$key] = $path.'.gql';
        } elseif (file_exists($path.'.graphql')) {
            $this->sdlPaths[$key] = $path.'.graphql';
        } else {
            throw new Exception('No file named "'.$fileName.'.gql" or "'.$fileName.'.graphql" found.');
        }

        // optionally add *.php file of schema
        if (file_exists($path.'.php')) {
            $this->phpPaths[$key] = $path.'.php';
        }
    }

    /**
     * Make hash of all schemas.
     *
     * @param  array  $items
     * @return void
     */
    protected function makeHash(array $items): void
    {
        $parts = [];

        foreach ($items as $item) {
            if ($item instanceof Schema) {
                // make hash of Schema instance
                $parts[] = 'hash:'.sha1(serialize($item));
            } else {
                // make hash of key
                $phpSuffix = isset($this->phpPaths[$item]) ? ':php' : '';
                $parts[] = $this->sdlPaths[$item].$phpSuffix;
            }
        }

        sort($parts);

        $this->hash = sha1(implode('::', $parts));
    }

    /**
     * Get path of schema sdl file.
     *
     * @param  string  $key
     * @return string
     */
    public function getSDLPath(string $key): string
    {
        return $this->sdlPaths[$key];
    }

    /**
     * Get path of schema php file.
     *
     * @param  string  $key
     * @return string
     */
    public function getPHPPath(string $key): ?string
    {
        return isset($this->phpPaths[$key])
            ? $this->phpPaths[$key]
            : null;
    }

    /**
     * Get all paths of all schemas.
     *
     * @return array
     */
    public function getPaths(): array
    {
        return array_merge(
            array_values($this->sdlPaths),
            array_values($this->phpPaths)
        );
    }

    /**
     * Get hash of all schemas.
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }
}
