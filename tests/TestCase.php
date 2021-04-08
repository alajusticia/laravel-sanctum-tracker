<?php

namespace ALajusticia\SanctumTracker\Tests;

use ALajusticia\Expirable\ExpirableServiceProvider;
use ALajusticia\SanctumTracker\SanctumTrackerServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();

        $this->artisan('migrate')->run();
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            SanctumTrackerServiceProvider::class,
            ExpirableServiceProvider::class,
            SanctumServiceProvider::class,
        ];
    }
}
