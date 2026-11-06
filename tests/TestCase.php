<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (! $app->environment('testing')) {
            throw new RuntimeException('The application test suite must run with APP_ENV=testing.');
        }

        if (! preg_match('/(^testing$|_tests?$|_testing$)/i', $database)) {
            throw new RuntimeException(
                "Refusing to run database-backed tests against unsafe database [{$database}]. "
                .'Use a dedicated database named testing or ending in _test/_testing.'
            );
        }

        return $app;
    }
}
