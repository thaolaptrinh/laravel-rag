<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Exceptions;

class NotImplementedException extends RagException
{
    public static function create(string $feature): self
    {
        return new self("Not implemented: {$feature}");
    }
}
