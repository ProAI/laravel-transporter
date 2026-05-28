<?php

namespace ProAI\Transporter;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ProAI\Transporter\Loaders\CustomLoader;
use ProAI\Transporter\Loaders\Loader;
use ProAI\Transporter\Loaders\RelationLoaderProxy;
use ProAI\Transporter\Loaders\RelationLoaderRepository;
use ProAI\Transporter\Middleware\AuthorizeResult;
use ReflectionFunction;

class Context
{
    /**
     * The application container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected Container $container;

    /**
     * The context's shared loader instances.
     *
     * @var array
     */
    protected array $loaders = [];

    /**
     * The context's shared relation loader instances.
     *
     * @var array
     */
    protected array $relationLoaders = [];

    /**
     * The context's shared custom loader instances.
     *
     * @var array
     */
    protected array $customLoaders = [];

    /**
     * The context's shared model cache.
     *
     * @var \ProAI\Transporter\ModelCache
     */
    protected ModelCache $models;

    /**
     * The pipeline instance for the context.
     *
     * @var \Illuminate\Pipeline\Pipeline
     */
    protected Pipeline $pipeline;

    /**
     * The context's middleware stack.
     *
     * @var array
     */
    protected array $middleware = [
        AuthorizeResult::class,
    ];

    /**
     * The context's shared resolver instances.
     *
     * @var array
     */
    protected array $resolvers = [];

    /**
     * The context's shared type resolver instances.
     *
     * @var array
     */
    protected array $typeResolvers = [];

    /**
     * The context's registered filter closures.
     *
     * @var array
     */
    protected array $filters = [];

    /**
     * The relation loader class resolver callback.
     *
     * @var \Closure
     */
    protected static Closure $relationLoaderClassResolver;

    /**
     * Create a new context instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->models = new ModelCache;

        $this->pipeline = new Pipeline($container);
    }

    /**
     * Register filter closure for loaders.
     *
     * @param  string  $key
     * @param  \Closure  $callback
     * @return void
     */
    public function registerFilter(string $key, Closure $callback): void
    {
        if (isset($this->filters[$key])) {
            throw new InvalidArgumentException('Filter "'.$key.'" has already been registered.');
        }

        $this->filters[$key] = $callback;
    }

    /**
     * Get loader instance for model.
     *
     * @param  string  $class
     * @return \ProAI\Transporter\Loaders\Loader
     */
    public function loader(string $class, mixed $constraints = null): Loader
    {
        $key = $this->getLoaderKey($class, $constraints);

        if (! isset($this->loaders[$key])) {
            $this->loaders[$key] = (new Loader($class, $constraints))->setCache($this->models);
        }

        return $this->loaders[$key];
    }

    /**
     * Get relation loader item instance for item.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $item
     * @param  string  $relation
     * @param  string|\Closure  $constraints
     * @return \ProAI\Transporter\Loaders\RelationLoaderProxy
     */
    public function relationLoader(Model $item, string $relation, mixed $constraints = null): RelationLoaderProxy
    {
        $class = $this->getRelationLoaderClass($item, $relation);

        if (is_string($constraints)) {
            $constraints = $this->filters[$constraints];
        }

        $key = $this->getLoaderKey($class, $constraints, $relation);

        if (! isset($this->relationLoaders[$key])) {
            $this->relationLoaders[$key] = (new RelationLoaderRepository(
                $class, $relation, $constraints
            ))->setCache($this->models);
        }

        return new RelationLoaderProxy($item, $this->relationLoaders[$key]);
    }

    /**
     * Get custom loader instance for the given closure.
     *
     * @param  \Closure  $closure
     * @return \ProAI\Transporter\Loaders\CustomLoader
     */
    public function customLoader(Closure $closure): CustomLoader
    {
        $key = $this->getClosureHash($closure);

        if (! isset($this->customLoaders[$key])) {
            $this->customLoaders[$key] = new CustomLoader($closure);
        }

        return $this->customLoaders[$key];
    }

    /**
     * Set the relation loader class resolver.
     *
     * @param  \Closure  $resolver
     * @return void
     */
    public static function setRelationLoaderClassResolver(Closure $resolver): void
    {
        static::$relationLoaderClassResolver = $resolver;
    }

    /**
     * Get class for relation loader.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $item
     * @param  string  $relation
     * @return string
     */
    protected function getRelationLoaderClass(Model $item, string $relation): string
    {
        if (isset(static::$relationLoaderClassResolver)) {
            return call_user_func(static::$relationLoaderClassResolver, $item, $relation);
        }

        return get_class($item);
    }

    /**
     * Get key for loader.
     *
     * @param  string  $class
     * @param  \Closure|null  $constraints
     * @param  string|null  $relation
     * @return string
     */
    protected function getLoaderKey(string $class, mixed $constraints = null, ?string $relation = null): string
    {
        $key = $class;

        if ($relation) {
            $key .= '.'.$relation;
        }

        if ($constraints) {
            $key .= '#'.$this->getClosureHash($constraints);
        }

        return $key;
    }

    /**
     * Get a unique hash for the given closure.
     *
     * @param  \Closure  $callback
     * @return string
     */
    protected function getClosureHash(Closure $callback): string
    {
        $reflector = new ReflectionFunction($callback);

        // Get the hash of closure related object.
        $objectHash = spl_object_hash($reflector->getClosureThis());

        // Get hash for closure definition.
        $definitionHash = $reflector->getClosureScopeClass()->getName().'@'.$reflector->getStartLine();

        // Get hash for arguments passed to closure.
        $arguments = array_map(function (mixed $argument) {
            if ($argument instanceof Collection) {
                return $argument->all();
            }

            return is_object($argument) ? spl_object_hash($argument) : $argument;
        }, $reflector->getStaticVariables());

        $argumentsHash = serialize($arguments);

        return md5($objectHash.':'.$definitionHash.':'.$argumentsHash);
    }

    /**
     * Call the given resolver.
     *
     * @param  callable|array|string  $callback
     * @param  array  $parameters
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function callResolver(callable|array|string $callback, array $parameters): mixed
    {
        if (is_string($callback)) {
            $callback = Str::parseCallback($callback, '__invoke');
        }

        if (is_array($callback)) {
            [$class, $method] = $callback;

            $key = $this->getFieldKey($parameters[3]);

            if (! isset($this->resolvers[$class])) {
                $this->resolvers[$class] = $this->container->make($class);
            }

            $callback = [$this->resolvers[$class], $method];
        }

        return $this->pipeline
            ->send($parameters)
            ->through($this->middleware)
            ->then(function (array $request) use ($callback) {
                return $callback(...$request);
            });
    }

    /**
     * Call the given type resolver.
     *
     * @param  callable|array|string  $callback
     * @param  array  $parameters
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function callTypeResolver(callable|array|string $callback, array $parameters): mixed
    {
        if (is_string($callback)) {
            $callback = Str::parseCallback($callback, '__invoke');
        }

        if (is_array($callback)) {
            [$class, $method] = $callback;

            $key = $this->getFieldKey($parameters[3]);

            if (! isset($this->typeResolvers[$class])) {
                $this->typeResolvers[$class] = $this->container->make($class);
            }

            $callback = [$this->typeResolvers[$class], $method];
        }

        return $callback(...$parameters);
    }

    /**
     * Get unique key for field.
     *
     * @param  \GraphQL\Type\Definition\ResolveInfo  $info
     * @return string
     */
    protected function getFieldKey(ResolveInfo $info): string
    {
        return $info->parentType->name.'.'.$info->fieldName;
    }

    /**
     * Get model cache.
     *
     * @return \ProAI\Transporter\ModelCache
     */
    public function getModelCache(): ModelCache
    {
        return $this->models;
    }
}
