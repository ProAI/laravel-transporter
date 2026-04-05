<?php

use ProAI\Transporter\TransporterServiceProvider;

it('registers transporter in the container', function () {
    expect(app()->bound('transporter'))->toBeTrue();
});

it('registers transporter cache in the container', function () {
    expect(app()->bound('transporter.cache'))->toBeTrue();
});

it('provides the correct services', function () {
    $provider = new TransporterServiceProvider(app());

    expect($provider->provides())->toBe([
        'transporter',
        'transporter.cache',
        'transporter.command.clear',
    ]);
});

it('registers the clear command', function () {
    // The clear command should be registered when running in console
    $this->artisan('transporter:clear')
        ->expectsOutput('Cached GraphQL schemas cleared!')
        ->assertExitCode(0);
});
