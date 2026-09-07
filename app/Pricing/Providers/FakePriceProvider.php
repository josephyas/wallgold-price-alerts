<?php

declare(strict_types=1);

namespace App\Pricing\Providers;

use App\Pricing\Contracts\PriceProvider;
use App\Pricing\Contracts\ScriptedPrices;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Stand-in for the external price feed: a bounded random walk that can be
 * steered with scripted prices for demos and tests.
 */
final class FakePriceProvider implements PriceProvider
{
    public const string SOURCE = 'fake';

    private Price $last;

    private readonly Randomizer $randomizer;

    public function __construct(
        Price $start,
        private readonly Price $maxStep,
        ?int $seed = null,
        private readonly ?ScriptedPrices $script = null,
    ) {
        $this->last = $start;
        $this->randomizer = new Randomizer($seed === null ? null : new Mt19937($seed));
    }

    public function fetch(): PriceQuote
    {
        // A scripted price becomes the new base of the walk, so the feed does
        // not jump back to the old level after a demo has steered it.
        $this->last = $this->script?->pull() ?? $this->step();

        return new PriceQuote($this->last, CarbonImmutable::now(), self::SOURCE);
    }

    private function step(): Price
    {
        $delta = $this->randomizer->getInt(-$this->maxStep->minor, $this->maxStep->minor);

        return Price::fromMinor(max(1, $this->last->minor + $delta));
    }
}
