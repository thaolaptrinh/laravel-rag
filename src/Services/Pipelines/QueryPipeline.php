<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services\Pipelines;

use Thaolaptrinh\Rag\Data\Answer;
use Thaolaptrinh\Rag\Exceptions\NotImplementedException;

class QueryPipeline
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function run(string $question, array $options = []): Answer
    {
        throw new NotImplementedException('Query pipeline');
    }

    /**
     * @param  callable(string): void  $callback
     * @param  array<string, mixed>  $options
     */
    public function runStream(string $question, callable $callback, array $options = []): Answer
    {
        throw new NotImplementedException('Query pipeline (streaming)');
    }
}
