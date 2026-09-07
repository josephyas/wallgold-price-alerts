<?php

declare(strict_types=1);

namespace App\Alerts\Index;

use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;

/** The last quote the watcher recorded, and when it was recorded. */
final readonly class CurrentPrice
{
    public function __construct(
        public PriceQuote $quote,
        public CarbonImmutable $receivedAt,
    ) {}

    public function receivedAtMs(): int
    {
        return (int) $this->receivedAt->getPreciseTimestamp(3);
    }

    /** Stale means the watcher has not recorded a price recently, whatever the feed's own timestamp says. */
    public function isStale(int $maxAgeMs, ?int $nowMs = null): bool
    {
        $nowMs ??= (int) CarbonImmutable::now()->getPreciseTimestamp(3);

        return $nowMs - $this->receivedAtMs() > $maxAgeMs;
    }
}
