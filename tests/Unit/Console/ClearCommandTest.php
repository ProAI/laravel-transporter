<?php

it('clears the schema cache', function () {
    $this->artisan('transporter:clear')
        ->expectsOutput('Cached GraphQL schemas cleared!')
        ->assertExitCode(0);
});
