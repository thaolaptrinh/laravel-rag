<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Data;

use Illuminate\Support\Str;

final readonly class Document
{
    private function __construct(
        public string $id,
        public string $content,
        /** @var array<string, mixed> */
        public array $metadata,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function create(string $content, array $metadata = [], ?string $id = null): self
    {
        return new self(
            id: $id ?? Str::uuid()->toString(),
            content: $content,
            metadata: $metadata,
        );
    }

    public function contentHash(): string
    {
        return hash('sha256', $this->content);
    }
}
