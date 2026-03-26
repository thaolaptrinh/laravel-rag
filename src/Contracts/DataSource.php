<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

interface DataSource
{
    /**
     * Load data from a source
     *
     * @param string $source Data source identifier (e.g., file path, URL, database query)
     * @return array<int, array{id: string, content: string, metadata: array<string, mixed>}>
     */
    public function load(string $source): array;
}
