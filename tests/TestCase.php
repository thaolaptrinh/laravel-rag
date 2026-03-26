<?php

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

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('rag.embedding.api_key', 'test-key');
        config()->set('rag.llm.api_key', 'test-key');
    }
}
