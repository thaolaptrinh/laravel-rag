<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag;

use Illuminate\Contracts\Container\Container;
use Thaolaptrinh\Rag\Data\Answer;
use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Data\IngestionResult;
use Thaolaptrinh\Rag\Jobs\RagIngestJob;
use Thaolaptrinh\Rag\Testing\FakeRagManager;

final class Rag
{
    private static ?Container $container = null;

    private static ?FakeRagManager $fake = null;

    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    private static function manager(): RagManager|FakeRagManager
    {
        if (self::$fake !== null) {
            return self::$fake;
        }

        $container = self::$container ?? Container::getInstance();

        return $container->make(RagManager::class);
    }

    public static function ingest(Document $document): IngestionResult
    {
        return self::manager()->ingest($document);
    }

    /**
     * @param  list<Document>  $documents
     */
    public static function ingestMany(array $documents): IngestionResult
    {
        return self::manager()->ingestMany($documents);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function query(string $question, array $options = []): Answer
    {
        return self::manager()->query($question, $options);
    }

    /**
     * @param  callable(string): void  $callback
     * @param  array<string, mixed>  $options
     */
    public static function queryStream(string $question, callable $callback, array $options = []): Answer
    {
        return self::manager()->queryStream($question, $callback, $options);
    }

    public static function delete(string $documentId): bool
    {
        return self::manager()->delete($documentId);
    }

    /**
     * @param  list<string>  $documentIds
     */
    public static function deleteMany(array $documentIds): int
    {
        return self::manager()->deleteMany($documentIds);
    }

    public static function truncate(): void
    {
        self::manager()->truncate();
    }

    public static function ingestQueued(Document $document, ?string $queue = null): void
    {
        dispatch(new RagIngestJob(
            documentId: $document->id,
            documentContent: $document->content,
            documentMetadata: $document->metadata,
            queue: $queue,
        ));
    }

    /**
     * @param  list<Document>  $documents
     */
    public static function ingestManyQueued(array $documents, ?string $queue = null): void
    {
        foreach ($documents as $document) {
            dispatch(new RagIngestJob(
                documentId: $document->id,
                documentContent: $document->content,
                documentMetadata: $document->metadata,
                queue: $queue,
            ));
        }
    }

    public static function fake(): void
    {
        self::$fake = new FakeRagManager;
    }

    public static function assertIngested(string $documentId): void
    {
        if (self::$fake === null) {
            throw new \RuntimeException('Rag::fake() must be called before using assert methods.');
        }

        self::$fake->assertIngested($documentId);
    }

    public static function assertQueried(string $question): void
    {
        if (self::$fake === null) {
            throw new \RuntimeException('Rag::fake() must be called before using assert methods.');
        }

        self::$fake->assertQueried($question);
    }

    public static function assertDeleted(string $documentId): void
    {
        if (self::$fake === null) {
            throw new \RuntimeException('Rag::fake() must be called before using assert methods.');
        }

        self::$fake->assertDeleted($documentId);
    }
}
