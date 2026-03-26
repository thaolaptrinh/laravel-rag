<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Facades;

use Illuminate\Support\Facades\Facade;
use Thaolaptrinh\Rag\Services\IngestionPipeline;
use Thaolaptrinh\Rag\Services\QueryPipeline;

/**
 * @method static array ingest(string $source)
 * @method static array query(string $query, int $topK = 5, array $filters = [])
 *
 * @see IngestionPipeline
 * @see QueryPipeline
 */
class Rag extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'rag';
    }
}
