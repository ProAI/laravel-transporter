<?php

namespace ProAI\Transporter\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use ProAI\Transporter\TransporterServiceProvider;

class TestCase extends OrchestraTestCase
{
    /**
     * @param  Application  $app
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TransporterServiceProvider::class,
        ];
    }
}
