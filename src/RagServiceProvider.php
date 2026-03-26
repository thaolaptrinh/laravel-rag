<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag;

use Illuminate\Support\ServiceProvider;
use Thaolaptrinh\Rag\Commands\IngestCommand;
use Thaolaptrinh\Rag\Commands\QueryCommand;
use Thaolaptrinh\Rag\Contracts\Chunker;
use Thaolaptrinh\Rag\Contracts\DataSource;
use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Contracts\LlmDriver;
use Thaolaptrinh\Rag\Contracts\PromptBuilder;
use Thaolaptrinh\Rag\Contracts\Retriever;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Drivers\DataSource\TextDataSource;
use Thaolaptrinh\Rag\Drivers\Embeddings\OpenAIEmbeddingDriver;
use Thaolaptrinh\Rag\Drivers\Llm\OpenAILlmDriver;
use Thaolaptrinh\Rag\Drivers\VectorStores\PgVectorStore;
use Thaolaptrinh\Rag\Services\IngestionPipeline;
use Thaolaptrinh\Rag\Services\Logging\RagLogger;
use Thaolaptrinh\Rag\Services\QueryPipeline;
use Thaolaptrinh\Rag\Services\Retrievers\SimilarityRetriever;
use Thaolaptrinh\Rag\Services\SimplePromptBuilder;
use Thaolaptrinh\Rag\Services\TextChunker;

class RagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/rag.php',
            'rag'
        );

        $this->app->bind(DataSource::class, function () {
            $config = config('rag.data_source', 'text');

            return match ($config) {
                'text' => new TextDataSource,
                default => throw new \InvalidArgumentException("Unsupported data source: {$config}"),
            };
        });

        $this->app->bind(Chunker::class, function () {
            $config = config('rag.chunker', []);
            $maxSize = $config['max_chunk_size'] ?? 1000;
            $overlap = $config['overlap'] ?? 200;

            return new TextChunker($maxSize, $overlap);
        });

        $this->app->bind(EmbeddingDriver::class, function () {
            $config = config('rag.embedding', []);
            $provider = $config['provider'] ?? 'openai';

            return match ($provider) {
                'openai' => new OpenAIEmbeddingDriver(
                    apiKey: $config['api_key'] ?? throw new \InvalidArgumentException('OpenAI API key is required'),
                    model: $config['model'] ?? 'text-embedding-3-small',
                    dimension: $config['dimension'] ?? 1536,
                    apiUrl: $config['api_url'] ?? null
                ),
                default => throw new \InvalidArgumentException("Unsupported embedding provider: {$provider}"),
            };
        });

        $this->app->bind(VectorStore::class, function () {
            $config = config('rag.vector_store', []);
            $provider = $config['provider'] ?? 'pgvector';

            return match ($provider) {
                'pgvector' => new PgVectorStore(
                    table: $config['table'] ?? 'rag_chunks'
                ),
                default => throw new \InvalidArgumentException("Unsupported vector store: {$provider}"),
            };
        });

        $this->app->bind(LlmDriver::class, function () {
            $config = config('rag.llm', []);
            $provider = $config['provider'] ?? 'openai';

            return match ($provider) {
                'openai' => new OpenAILlmDriver(
                    apiKey: $config['api_key'] ?? throw new \InvalidArgumentException('OpenAI API key is required'),
                    model: $config['model'] ?? 'gpt-4o-mini',
                    maxTokens: $config['max_tokens'] ?? 4096,
                    temperature: $config['temperature'] ?? 0.7,
                    apiUrl: $config['api_url'] ?? null
                ),
                default => throw new \InvalidArgumentException("Unsupported LLM provider: {$provider}"),
            };
        });

        $this->app->bind(PromptBuilder::class, function () {
            $config = config('rag.prompt', []);
            $system = $config['system'] ?? 'You are a helpful assistant. Answer the question based on the provided context.';
            $averageTokensPerWord = $config['average_tokens_per_word'] ?? 4;

            return new SimplePromptBuilder($system, $averageTokensPerWord);
        });

        $this->app->bind(Retriever::class, function ($app) {
            $config = config('rag.retriever', []);
            $type = $config['type'] ?? 'similarity';

            return match ($type) {
                'similarity' => new SimilarityRetriever(
                    $app->make(EmbeddingDriver::class),
                    $config['table'] ?? 'rag_chunks'
                ),
                default => throw new \InvalidArgumentException("Unsupported retriever type: {$type}"),
            };
        });

        $this->app->singleton(RagLogger::class, function () {
            return new RagLogger;
        });

        $this->app->singleton(IngestionPipeline::class, function ($app) {
            return new IngestionPipeline(
                $app->make(DataSource::class),
                $app->make(Chunker::class),
                $app->make(EmbeddingDriver::class),
                $app->make(VectorStore::class),
                $app->make(RagLogger::class)
            );
        });

        $this->app->singleton(QueryPipeline::class, function ($app) {
            return new QueryPipeline(
                $app->make(Retriever::class),
                $app->make(PromptBuilder::class),
                $app->make(LlmDriver::class),
                $app->make(RagLogger::class)
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/rag.php' => config_path('rag.php'),
            ], 'rag-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'rag-migrations');

            $this->commands([
                IngestCommand::class,
                QueryCommand::class,
            ]);
        }
    }
}
