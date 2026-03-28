<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Thaolaptrinh\Rag\RagServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            RagServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
    }
}
