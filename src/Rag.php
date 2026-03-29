<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag;

use Illuminate\Contracts\Container\Container;
use Thaolaptrinh\Rag\Data\Answer;
use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Data\IngestionResult;

final class Rag
{
    private static ?Container $container = null;

    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    private static function manager(): RagManager
    {
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
}
