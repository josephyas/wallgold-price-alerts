<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Pricing\Exceptions\PriceUnavailable;
use App\Pricing\Providers\GoldApiPriceProvider;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GoldApiPriceProviderTest extends TestCase
{
    private const string URL = 'https://www.goldapi.io/api/XAU/USD';

    #[Test]
    public function it_maps_a_successful_response_to_a_quote(): void
    {
        Http::fake([self::URL => Http::response(['price' => 2650.125, 'timestamp' => 1_788_782_400, 'metal' => 'XAU'])]);

        $quote = $this->provider()->fetch();

        self::assertSame('2650.1250', $quote->price->toDecimal());
        self::assertSame('2026-09-07T12:00:00+00:00', $quote->observedAt->toIso8601String());
        self::assertSame(GoldApiPriceProvider::SOURCE, $quote->source);

        Http::assertSent(fn (Request $request): bool => $request->url() === self::URL
            && $request->hasHeader('x-access-token', 'secret-token')
            && $request->hasHeader('Accept', 'application/json'));
    }

    #[Test]
    public function it_falls_back_to_the_current_time_without_a_timestamp(): void
    {
        Http::fake([self::URL => Http::response(['price' => '2650'])]);

        $quote = $this->provider()->fetch();

        self::assertEqualsWithDelta(time(), $quote->observedAt->getTimestamp(), 5);
    }

    #[Test]
    public function it_reports_http_errors_as_unavailable(): void
    {
        Http::fake([self::URL => Http::response(['error' => 'quota'], 500)]);

        $this->expectException(PriceUnavailable::class);
        $this->expectExceptionMessage('HTTP 500');

        $this->provider()->fetch();
    }

    #[Test]
    public function it_reports_connection_failures_as_unavailable(): void
    {
        Http::fake([self::URL => Http::failedConnection()]);

        $this->expectException(PriceUnavailable::class);

        $this->provider()->fetch();
    }

    #[Test]
    public function it_reports_a_missing_price_as_unavailable(): void
    {
        Http::fake([self::URL => Http::response(['metal' => 'XAU'])]);

        $this->expectException(PriceUnavailable::class);
        $this->expectExceptionMessage('numeric price');

        $this->provider()->fetch();
    }

    #[Test]
    public function it_reports_an_implausible_price_as_unavailable(): void
    {
        Http::fake([self::URL => Http::response(['price' => 0])]);

        $this->expectException(PriceUnavailable::class);
        $this->expectExceptionMessage('implausible');

        $this->provider()->fetch();
    }

    private function provider(): GoldApiPriceProvider
    {
        return new GoldApiPriceProvider($this->app->make(Factory::class), self::URL, 'secret-token');
    }
}
