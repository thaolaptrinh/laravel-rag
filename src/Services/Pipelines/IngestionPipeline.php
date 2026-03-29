<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services\Pipelines;

use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Data\IngestionResult;
use Thaolaptrinh\Rag\Exceptions\NotImplementedException;

class IngestionPipeline
{
    public function run(Document ...$documents): IngestionResult
    {
        throw new NotImplementedException('Ingestion pipeline');
    }
}
