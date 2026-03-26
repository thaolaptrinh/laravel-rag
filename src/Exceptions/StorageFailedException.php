<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Exceptions;

class StorageFailedException extends RagException
{
    public static function create(string $reason): self
    {
        return new self("Vector storage failed: {$reason}");
    }
}
