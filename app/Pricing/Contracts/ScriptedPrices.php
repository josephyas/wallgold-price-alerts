<?php

declare(strict_types=1);

namespace App\Pricing\Contracts;

use App\Pricing\Price;

/**
 * A queue of prices that the fake provider serves before continuing its random
 * walk, so a demo can steer the price to an exact value.
 */
interface ScriptedPrices
{
    /** Take the next scripted price, or null when nothing is queued. */
    public function pull(): ?Price;

    public function push(Price ...$prices): void;
}
