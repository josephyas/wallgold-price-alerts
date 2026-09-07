<?php

declare(strict_types=1);

namespace App\Pricing\Contracts;

use App\Pricing\Exceptions\PriceUnavailable;
use App\Pricing\PriceQuote;

/**
 * Source of the global gold price.
 */
interface PriceProvider
{
    /**
     * @throws PriceUnavailable when no trustworthy price can be produced right now
     */
    public function fetch(): PriceQuote;
}
