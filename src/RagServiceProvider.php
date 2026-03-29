<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag;

use Illuminate\Support\Facades\Config;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Thaolaptrinh\Rag\Commands\RagDeleteCommand;
use Thaolaptrinh\Rag\Commands\RagIngestCommand;
use Thaolaptrinh\Rag\Commands\RagInstallCommand;
use Thaolaptrinh\Rag\Commands\RagQueryCommand;
use Thaolaptrinh\Rag\Contracts\Chunker;
use Thaolaptrinh\Rag\Contracts\ContextEnricher;
use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Contracts\LlmDriver;
use Thaolaptrinh\Rag\Contracts\PromptBuilder;
use Thaolaptrinh\Rag\Contracts\Retriever;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Drivers\Embeddings\HttpEmbeddingDriver;
use Thaolaptrinh\Rag\Drivers\Enrichment\LlmContextEnricher;
use Thaolaptrinh\Rag\Drivers\Llm\HttpLlmDriver;
use Thaolaptrinh\Rag\Drivers\VectorStores\PgVectorStore;
use Thaolaptrinh\Rag\Services\Chunking\FixedSizeChunker;
use Thaolaptrinh\Rag\Services\Pipelines\IngestionPipeline;
use Thaolaptrinh\Rag\Services\Pipelines\QueryPipeline;
use Thaolaptrinh\Rag\Services\Prompt\SimplePromptBuilder;
use Thaolaptrinh\Rag\Services\RagLogger;
use Thaolaptrinh\Rag\Services\Retrieving\SimilarityRetriever;

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
        $this->registerLogger();
        $this->registerDrivers();
        $this->registerServices();
        $this->registerManager();

        Rag::setContainer($this->app);
    }

    public function boot(): void
    {
        parent::boot();

        $this->commands([
            RagIngestCommand::class,
            RagQueryCommand::class,
            RagDeleteCommand::class,
            RagInstallCommand::class,
        ]);
    }

    private function registerLogger(): void
    {
        $this->app->singleton(RagLogger::class, function (): RagLogger {
            return new RagLogger(
                channel: self::configString('rag.logging.channel', 'stack'),
            );
        });
    }

    private function registerDrivers(): void
    {
        $this->app->singleton(EmbeddingDriver::class, function (): HttpEmbeddingDriver {
            return new HttpEmbeddingDriver(
                apiKey: self::configString('rag.embedding.api_key', ''),
                model: self::configString('rag.embedding.model', 'text-embedding-3-small'),
                dimensions: self::configPositiveInt('rag.embedding.dimensions', 1536),
                batchSize: self::configPositiveInt('rag.embedding.batch_size', 100),
                timeout: self::configPositiveInt('rag.embedding.timeout', 120),
                apiUrl: self::configString('rag.embedding.api_url', 'https://api.openai.com/v1/embeddings'),
            );
        });

        $this->app->singleton(LlmDriver::class, function (): HttpLlmDriver {
            return new HttpLlmDriver(
                apiKey: self::configString('rag.llm.api_key', ''),
                model: self::configString('rag.llm.model', 'gpt-4o-mini'),
                maxOutputTokens: self::configPositiveInt('rag.llm.max_output_tokens', 4096),
                contextWindow: self::configPositiveInt('rag.llm.context_window', 128000),
                temperature: self::configFloat('rag.llm.temperature', 0.7),
                timeout: self::configPositiveInt('rag.llm.timeout', 120),
                apiUrl: self::configString('rag.llm.api_url', 'https://api.openai.com/v1/chat/completions'),
            );
        });

        $this->app->singleton(VectorStore::class, function (): PgVectorStore {
            return new PgVectorStore(
                connection: self::configString('rag.database.connection', 'rag'),
                documentsTable: self::configString('rag.database.documents_table', 'rag_documents'),
                chunksTable: self::configString('rag.database.chunks_table', 'rag_chunks'),
                hnswEfSearch: self::configPositiveInt('rag.retrieval.hnsw_ef_search', 100),
            );
        });

        $this->app->singleton(Retriever::class, function ($app): SimilarityRetriever {
            return new SimilarityRetriever(
                embedder: $app->make(EmbeddingDriver::class),
                store: $app->make(VectorStore::class),
                minScore: self::configFloat('rag.retrieval.min_score', 0.0),
            );
        });

        $this->app->singleton(PromptBuilder::class, function (): SimplePromptBuilder {
            return new SimplePromptBuilder(
                tokensPerChar: self::configFloat('rag.prompt.tokens_per_char', 0.25),
            );
        });

        $this->app->bind(Chunker::class, function (): FixedSizeChunker {
            return new FixedSizeChunker(
                chunkSize: self::configPositiveInt('rag.chunking.chunk_size', 1000),
                overlap: self::configNonNegativeInt('rag.chunking.overlap', 200),
            );
        });

        if (self::configBool('rag.contextual.enabled')) {
            $this->app->singleton(ContextEnricher::class, function ($app): LlmContextEnricher {
                return new LlmContextEnricher(
                    llm: $app->make(LlmDriver::class),
                );
            });
        }
    }

    private function registerServices(): void
    {
        $this->app->singleton(IngestionPipeline::class, function ($app): IngestionPipeline {
            return new IngestionPipeline(
                chunker: $app->make(Chunker::class),
                embeddingDriver: $app->make(EmbeddingDriver::class),
                vectorStore: $app->make(VectorStore::class),
                logger: $app->make(RagLogger::class),
                contextEnricher: $app->bound(ContextEnricher::class) ? $app->make(ContextEnricher::class) : null,
                maxContentLength: self::configPositiveInt('rag.document.max_content_length', 100_000),
                subBatchSize: self::configPositiveInt('rag.ingestion.sub_batch_size', 10),
                pipelineTimeout: self::configPositiveInt('rag.ingestion.pipeline_timeout', 600),
                connection: self::configString('rag.database.connection', 'rag'),
            );
        });

        $this->app->singleton(QueryPipeline::class, function ($app): QueryPipeline {
            return new QueryPipeline(
                retriever: $app->make(Retriever::class),
                promptBuilder: $app->make(PromptBuilder::class),
                llmDriver: $app->make(LlmDriver::class),
                logger: $app->make(RagLogger::class),
            );
        });
    }

    private function registerManager(): void
    {
        $this->app->singleton(RagManager::class, function ($app): RagManager {
            return new RagManager(
                ingestionPipeline: $app->make(IngestionPipeline::class),
                queryPipeline: $app->make(QueryPipeline::class),
                vectorStore: $app->make(VectorStore::class),
            );
        });

        $this->app->singleton('rag', RagManager::class);
    }

    private static function configString(string $key, string $default): string
    {
        $value = Config::get($key, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * @param  int<1, max>  $default
     * @return int<1, max>
     */
    private static function configPositiveInt(string $key, int $default): int
    {
        $value = Config::get($key, $default);

        if (is_int($value) && $value >= 1) {
            return $value;
        }

        return $default;
    }

    /**
     * @param  int<0, max>  $default
     * @return int<0, max>
     */
    private static function configNonNegativeInt(string $key, int $default): int
    {
        $value = Config::get($key, $default);

        if (is_int($value) && $value >= 0) {
            return $value;
        }

        return $default;
    }

    private static function configFloat(string $key, float $default): float
    {
        $value = Config::get($key, $default);

        return is_float($value) || is_int($value) ? (float) $value : $default;
    }

    private static function configBool(string $key): bool
    {
        $value = Config::get($key, false);

        return is_bool($value) && $value;
    }
}
