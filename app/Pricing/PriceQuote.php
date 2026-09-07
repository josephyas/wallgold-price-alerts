<?php

declare(strict_types=1);

namespace App\Pricing;

use Carbon\CarbonImmutable;

/**
 * A price observation: what the price was, when it was observed and where it came from.
 */
final readonly class PriceQuote
{
    public function __construct(
        public Price $price,
        public CarbonImmutable $observedAt,
        public string $source,
    ) {}

    public function observedAtMs(): int
    {
        return (int) $this->observedAt->getPreciseTimestamp(3);
    }
}
