<?php

namespace ProAI\Transporter\Console;

use Illuminate\Console\Command;
use ProAI\Transporter\Schema\Cache;

class ClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'transporter:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all cached GraphQL schema files';

    /**
     * The schema cache instance.
     *
     * @var \ProAI\Transporter\Schema\Cache
     */
    protected Cache $cache;

    /**
     * Create a new transporter clear command instance.
     *
     * @param  \ProAI\Transporter\Schema\Cache  $cache
     * @return void
     */
    public function __construct(Cache $cache)
    {
        parent::__construct();

        $this->cache = $cache;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->cache->flush();

        $this->info('Cached GraphQL schemas cleared!');
    }
}
