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

    /**
     * Whether the observation is older than the given age, measured against
     * an explicit "now" so callers (and tests) control the clock.
     */
    public function isOlderThan(int $maxAgeMs, ?int $nowMs = null): bool
    {
        $nowMs ??= (int) CarbonImmutable::now()->getPreciseTimestamp(3);

        return $nowMs - $this->observedAtMs() > $maxAgeMs;
    }
}
