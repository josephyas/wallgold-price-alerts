<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Pricing\Price;
use App\Pricing\Providers\FakePriceProvider;
use App\Pricing\Providers\InMemoryScriptedPrices;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FakePriceProviderTest extends TestCase
{
    #[Test]
    public function it_walks_deterministically_for_a_seed(): void
    {
        $first = $this->walk(new FakePriceProvider(Price::fromDecimal('2650'), Price::fromDecimal('2'), seed: 42));
        $second = $this->walk(new FakePriceProvider(Price::fromDecimal('2650'), Price::fromDecimal('2'), seed: 42));
        $other = $this->walk(new FakePriceProvider(Price::fromDecimal('2650'), Price::fromDecimal('2'), seed: 43));

        self::assertSame($first, $second);
        self::assertNotSame($first, $other);
    }

    #[Test]
    public function each_step_stays_within_the_configured_bound(): void
    {
        $provider = new FakePriceProvider(Price::fromDecimal('2650'), Price::fromDecimal('0.25'), seed: 7);
        $previous = $provider->fetch()->price;

        foreach (range(1, 200) as $_) {
            $quote = $provider->fetch();

            self::assertLessThanOrEqual(2_500, abs($quote->price->minor - $previous->minor));
            self::assertSame(FakePriceProvider::SOURCE, $quote->source);

            $previous = $quote->price;
        }
    }

    #[Test]
    public function it_never_drops_to_zero(): void
    {
        $provider = new FakePriceProvider(Price::fromMinor(1), Price::fromDecimal('5'), seed: 1);

        foreach (range(1, 50) as $_) {
            self::assertGreaterThanOrEqual(1, $provider->fetch()->price->minor);
        }
    }

    #[Test]
    public function scripted_prices_are_served_first_and_become_the_new_base(): void
    {
        $script = new InMemoryScriptedPrices;
        $script->push(Price::fromDecimal('2690'), Price::fromDecimal('2701.25'));

        $provider = new FakePriceProvider(Price::fromDecimal('2650'), Price::fromDecimal('1'), seed: 3, script: $script);

        self::assertSame('2690.0000', $provider->fetch()->price->toDecimal());
        self::assertSame('2701.2500', $provider->fetch()->price->toDecimal());

        $next = $provider->fetch()->price;

        self::assertLessThanOrEqual(10_000, abs($next->minor - 27_012_500), 'the walk continues from the last scripted price');
    }

    /**
     * @return list<string>
     */
    private function walk(FakePriceProvider $provider): array
    {
        return array_map(fn (): string => $provider->fetch()->price->toDecimal(), range(1, 20));
    }
}
