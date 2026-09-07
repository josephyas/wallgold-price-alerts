<?php

declare(strict_types=1);

namespace App\Pricing\Exceptions;

use RuntimeException;
use Throwable;

final class PriceUnavailable extends RuntimeException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
