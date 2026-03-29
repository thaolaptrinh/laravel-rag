<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Exceptions;

final class GenerationFailedException extends RagException
{
    public static function create(string $reason, ?\Throwable $previous = null): self
    {
        return new self("Generation failed: {$reason}", 0, $previous);
    }
}
