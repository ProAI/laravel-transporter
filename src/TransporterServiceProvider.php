<?php

namespace ProAI\Transporter;

use GraphQL\Executor\Executor as GraphQLExecutor;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use ProAI\Transporter\Console\ClearCommand;
use ProAI\Transporter\Middleware\AuthorizeResult;
use ProAI\Transporter\Resolvers\DefaultResolver;
use ProAI\Transporter\Schema\Cache as SchemaCache;
use ProAI\Transporter\Type\FieldResolverAdapter;

class TransporterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        // Set the transporter default field resolver.
        GraphQLExecutor::setDefaultFieldResolver(
            new FieldResolverAdapter(DefaultResolver::class)
        );
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerTransporterCache();
        $this->registerTransporter();

        $this->registerMiddleware();

        if ($this->app->runningInConsole()) {
            $this->registerTransporterClearCommand();

            $this->commands(
                'transporter.command.clear'
            );
        }
    }

    /**
     * Register the transporter container.
     *
     * @return void
     */
    protected function registerTransporterCache(): void
    {
        $this->app->singleton('transporter.cache', function (Application $app) {
            $cachePath = realpath(storage_path('framework/graphql'));

            return new SchemaCache($app['files'], $cachePath);
        });
    }

    /**
     * Register the transporter singleton.
     *
     * @return void
     */
    protected function registerTransporter(): void
    {
        $this->app->singleton('transporter', function (Application $app) {
            $path = $this->getGraphQLPath();

            return new Transporter(
                $app,
                $app->make(ExceptionHandler::class),
                $app['files'],
                $app['transporter.cache'],
                $path
            );
        });

        $this->app->alias('transporter', Transporter::class);
    }

    /**
     * Register the middleware singleton.
     *
     * @return void
     */
    protected function registerMiddleware(): void
    {
        $this->app->singleton(AuthorizeResult::class);
    }

    /**
     * Register the transporter:clear command.
     *
     * @return void
     */
    protected function registerTransporterClearCommand(): void
    {
        $this->app->singleton('transporter.command.clear', function (Application $app) {
            return new ClearCommand($app['transporter.cache']);
        });
    }

    /**
     * Get the path to the graphql directory.
     *
     * @return string
     */
    protected function getGraphQLPath(): string
    {
        return resource_path('graphql');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            'transporter',
            'transporter.cache',
            'transporter.command.clear',
        ];
    }
}
