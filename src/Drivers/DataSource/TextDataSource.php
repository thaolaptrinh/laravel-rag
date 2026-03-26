<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Drivers\DataSource;

use Illuminate\Support\Str;
use Thaolaptrinh\Rag\Contracts\DataSource;

class TextDataSource implements DataSource
{
    /**
     * Load text from a file
     *
     * @param string $source File path
     * @return array<int, array{id: string, content: string, metadata: array<string, mixed>}>
     */
    public function load(string $source): array
    {
        if (!file_exists($source)) {
            throw new \RuntimeException("File not found: {$source}");
        }

        if (!is_readable($source)) {
            throw new \RuntimeException("File not readable: {$source}");
        }

        $content = file_get_contents($source);

        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$source}");
        }

        return [
            [
                'id' => Str::uuid()->toString(),
                'content' => $content,
                'metadata' => [
                    'source' => $source,
                    'type' => 'text',
                    'size' => strlen($content),
                ],
            ],
        ];
    }
}
