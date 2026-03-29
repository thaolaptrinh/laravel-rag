<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag;

use Illuminate\Support\Facades\Config;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Services\Pipelines\IngestionPipeline;
use Thaolaptrinh\Rag\Services\Pipelines\QueryPipeline;
use Thaolaptrinh\Rag\Services\RagLogger;

class RagServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-rag')
            ->hasConfigFile()
            ->hasMigrations(
                '2026_03_28_000001_create_rag_documents_table',
                '2026_03_28_000002_create_rag_chunks_table',
                '2026_03_28_000003_create_rag_chunks_indexes',
            );
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(RagLogger::class, function (): RagLogger {
            return new RagLogger(
                channel: Config::string('rag.logging.channel', 'stack'),
            );
        });

        $this->app->singleton(IngestionPipeline::class);
        $this->app->singleton(QueryPipeline::class);

        $this->app->singleton('rag', RagManager::class);
        $this->app->singleton(RagManager::class, function ($app): RagManager {
            return new RagManager(
                ingestionPipeline: $app->make(IngestionPipeline::class),
                queryPipeline: $app->make(QueryPipeline::class),
                vectorStore: $app->make(VectorStore::class),
            );
        });

        Rag::setContainer($this->app);
    }
}
