<?php

declare(strict_types=1);

namespace App\Alerts;

use App\Pricing\Price;

/** The side an alert watches, and the price it was compared against when created. */
final readonly class ResolvedDirection
{
    public function __construct(
        public Direction $direction,
        public ?Price $reference,
    ) {}
}
