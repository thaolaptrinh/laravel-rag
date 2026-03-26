<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Exceptions;

class RetrievalFailedException extends RagException
{
    public static function create(string $reason): self
    {
        return new self("Retrieval failed: {$reason}");
    }
}
