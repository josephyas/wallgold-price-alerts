<?php

declare(strict_types=1);

namespace App\Alerts;

/** What one price tick did. */
final readonly class MatchOutcome
{
    public function __construct(
        public int $matched,
        public int $batches,
        public bool $rebuilt,
    ) {}
}
