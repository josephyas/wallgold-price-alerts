<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PriceQuoteTest extends TestCase
{
    #[Test]
    public function it_exposes_the_observation_time_in_milliseconds(): void
    {
        $quote = new PriceQuote(Price::fromDecimal('2650'), CarbonImmutable::parse('2026-09-07 12:00:00.250', 'UTC'), 'fake');

        self::assertSame(1_788_782_400_250, $quote->observedAtMs());
    }

    #[Test]
    public function it_survives_serialisation_for_queued_jobs(): void
    {
        $quote = new PriceQuote(Price::fromDecimal('2701.25'), CarbonImmutable::parse('2026-09-07 12:00:00', 'UTC'), 'fake');

        $restored = unserialize(serialize($quote));

        self::assertInstanceOf(PriceQuote::class, $restored);
        self::assertTrue($restored->price->equals($quote->price));
        self::assertTrue($restored->observedAt->equalTo($quote->observedAt));
        self::assertSame('fake', $restored->source);
    }
}
