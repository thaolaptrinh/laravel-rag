<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Exceptions;

class EmbeddingFailedException extends RagException
{
    public static function create(string $reason): self
    {
        return new self("Embedding generation failed: {$reason}");
    }
}
