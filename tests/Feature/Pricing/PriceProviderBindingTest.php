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
