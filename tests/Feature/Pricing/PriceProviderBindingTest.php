<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Pricing\Contracts\PriceProvider;
use App\Pricing\Providers\FakePriceProvider;
use App\Pricing\Providers\GoldApiPriceProvider;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PriceProviderBindingTest extends TestCase
{
    #[Test]
    public function the_fake_provider_is_the_default_and_a_singleton(): void
    {
        config()->set('gold.provider', 'fake');

        $provider = $this->app->make(PriceProvider::class);

        self::assertInstanceOf(FakePriceProvider::class, $provider);
        self::assertSame($provider, $this->app->make(PriceProvider::class));
    }

    #[Test]
    public function an_empty_seed_means_a_fresh_walk_and_an_integer_a_repeatable_one(): void
    {
        config()->set('gold.provider', 'fake');

        config()->set('gold.fake.seed', '');
        self::assertNotSame($this->walk(), $this->walk(), 'unseeded walks differ');

        config()->set('gold.fake.seed', '42');
        self::assertSame($this->walk(), $this->walk(), 'seeded walks repeat');
    }

    /**
     * @return list<string>
     */
    private function walk(): array
    {
        $this->app->forgetInstance(PriceProvider::class);
        $provider = $this->app->make(PriceProvider::class);

        return array_map(fn (): string => $provider->fetch()->price->toDecimal(), range(1, 20));
    }

    #[Test]
    public function the_goldapi_provider_can_be_selected(): void
    {
        config()->set('gold.provider', 'goldapi');

        self::assertInstanceOf(GoldApiPriceProvider::class, $this->app->make(PriceProvider::class));
    }

    #[Test]
    public function an_unknown_provider_is_rejected(): void
    {
        config()->set('gold.provider', 'crystal-ball');

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(PriceProvider::class);
    }
}
