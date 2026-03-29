<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Exceptions;

final class ConfigurationException extends RagException
{
    public static function create(string $reason, ?\Throwable $previous = null): self
    {
        return new self("Configuration error: {$reason}", 0, $previous);
    }
}
