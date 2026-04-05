<?php

namespace ProAI\Transporter;

use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Filesystem\Filesystem;
use ProAI\Transporter\Schema\Cache as SchemaCache;
use ProAI\Transporter\Schema\Factory as SchemaFactory;
use ProAI\Transporter\Schema\Merger as SchemaMerger;
use ProAI\Transporter\Schema\Source as SchemaSource;

class Transporter
{
    /**
     * The application container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected Container $container;

    /**
     * The exception handler instance.
     *
     * @var \Illuminate\Contracts\Debug\ExceptionHandler
     */
    protected ExceptionHandler $exceptionHandler;

    /**
     * The Filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected Filesystem $files;

    /**
     * The cache instance.
     *
     * @var \ProAI\Transporter\Schema\Cache
     */
    protected SchemaCache $cache;

    /**
     * The path to the graphql directory.
     *
     * @var string
     */
    protected string $path;

    /**
     * Whether or not a (parent) model must have a policy.
     *
     * @var bool
     */
    public static bool $enforcedPolicies = false;

    /**
     * The identifier field name.
     *
     * @var string
     */
    public static string $identifierField = 'id';

    /**
     * Indicates whether the normalized result format is used.
     *
     * @var bool
     */
    public static bool $normalizedResult = false;

    /**
     * Class name of the error handler class.
     *
     * @var string
     */
    public static string $errorHandler = ErrorHandler::class;

    /**
     * Create a new transporter instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @param  \Illuminate\Contracts\Debug\ExceptionHandler  $exceptionHandler
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  \ProAI\Transporter\Schema\Cache  $cache
     * @param  string  $path
     * @return void
     */
    public function __construct(Container $container,
        ExceptionHandler $exceptionHandler,
        Filesystem $files,
        SchemaCache $cache,
        string $path)
    {
        $this->container = $container;
        $this->exceptionHandler = $exceptionHandler;
        $this->files = $files;
        $this->cache = $cache;

        $this->path = $path;
    }

    /**
     * Build a schema.
     *
     * @param  string  $key
     * @return \GraphQL\Type\Schema
     */
    public function buildSchema(string $key): Schema
    {
        $source = new SchemaSource($this->path, [$key]);

        // get cached version if cache has not been expired
        if (! $this->cache->isExpired($source)) {
            return $this->cache->get($source);
        }

        $schema = $this->newSchemaFactory($key, $source)->make();

        $this->cache->put($source, $schema);

        return $schema;
    }

    /**
     * Merge schemas.
     *
     * @param  string[]  $items
     * @return \GraphQL\Type\Schema
     */
    public function mergeSchemas(array $items): Schema
    {
        $source = new SchemaSource($this->path, $items);

        // get cached version if cache has not been expired
        if (! $this->cache->isExpired($source)) {
            return $this->cache->get($source);
        }

        $schema = $this->newSchemaMerger($items, $source)->merge();

        $this->cache->put($source, $schema);

        return $schema;
    }

    /**
     * Create a new merger instance.
     *
     * @param  array  $items
     * @param  \ProAI\Transporter\Schema\Source  $source
     * @return \ProAI\Transporter\Schema\Merger
     */
    protected function newSchemaMerger(array $items, SchemaSource $source): SchemaMerger
    {
        $schemas = [];
        $factories = [];

        foreach ($items as $item) {
            if ($item instanceof Schema) {
                $schemas[] = $item;
            } else {
                $factories[] = $this->newSchemaFactory($item, $source);
            }
        }

        return new SchemaMerger($schemas, $factories);
    }

    /**
     * Create a new schema factory instance.
     *
     * @param  string  $key
     * @param  \ProAI\Transporter\Schema\Source  $source
     * @return \ProAI\Transporter\Schema\Factory
     */
    protected function newSchemaFactory(string $key, SchemaSource $source): SchemaFactory
    {
        $astNode = Parser::parse(
            $this->files->get($source->getSDLPath($key)),
            ['noLocation' => true]
        );

        $instance = new SchemaFactory($astNode);

        // include php file if present
        if ($phpPath = $source->getPHPPath($key)) {
            $instance->includeFile($phpPath);
        }

        return $instance;
    }

    /**
     * Process graphql request.
     *
     * @param  \GraphQL\Type\Schema  $schema
     * @param  string  $source
     * @param  mixed|null  $rootValue
     * @param  mixed|null  $contextValue
     * @param  array|null  $variableValues
     * @param  string|null  $operationName
     * @param  callable|null  $fieldResolver
     * @param  \GraphQL\Validator\Rules\ValidationRule[]|null  $validationRules
     * @return array
     */
    public function graphql(Schema $schema,
        string $source,
        mixed $rootValue = null,
        mixed $contextValue = null,
        ?array $variableValues = null,
        ?string $operationName = null,
        ?callable $fieldResolver = null,
        ?array $validationRules = null): array
    {
        $executor = static::$normalizedResult
            ? Normalizer::class
            : GraphQL::class;

        $context = new Context($this->container);

        $result = $executor::executeQuery(
            $schema,
            $source,
            $rootValue,
            $context,
            $variableValues,
            $operationName,
            $fieldResolver,
            $validationRules
        );

        $result->setErrorsHandler(new static::$errorHandler($this->exceptionHandler));

        if (config('app.debug')) {
            $debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
        } else {
            $debug = DebugFlag::NONE;
        }

        return $result->toArray($debug);
    }
}
