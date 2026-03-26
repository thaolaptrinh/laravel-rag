<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Exceptions;

class GenerationFailedException extends RagException
{
    public static function create(string $reason): self
    {
        return new self("LLM generation failed: {$reason}");
    }
}
