<?php

declare(strict_types=1);

namespace App\Alerts\Index;

use App\Alerts\Direction;
use App\Pricing\Price;

/** What the index needs to know about an active alert. */
final readonly class IndexEntry
{
    public function __construct(
        public int $id,
        public Direction $direction,
        public Price $target,
    ) {}
}
