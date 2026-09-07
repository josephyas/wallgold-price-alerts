<?php

declare(strict_types=1);

namespace App\Pricing\Providers;

use App\Pricing\Contracts\PriceProvider;
use App\Pricing\Exceptions\PriceUnavailable;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use InvalidArgumentException;

/**
 * Reads XAU/USD from goldapi.io. Any failure, malformed body or implausible
 * value is reported as PriceUnavailable so the caller can back off.
 */
final class GoldApiPriceProvider implements PriceProvider
{
    public const string SOURCE = 'goldapi';

    public function __construct(
        private readonly Http $http,
        private readonly string $url,
        private readonly string $token,
        private readonly float $timeout = 2.0,
    ) {}

    public function fetch(): PriceQuote
    {
        try {
            $response = $this->http
                ->withHeaders(['x-access-token' => $this->token])
                ->acceptJson()
                ->connectTimeout(1)
                ->timeout($this->timeout)
                ->get($this->url);
        } catch (ConnectionException $e) {
            throw PriceUnavailable::because('GoldAPI request failed: '.$e->getMessage(), $e);
        }

        if ($response->failed()) {
            throw PriceUnavailable::because(sprintf('GoldAPI responded with HTTP %d.', $response->status()));
        }

        $price = $response->json('price');

        if (! is_numeric($price)) {
            throw PriceUnavailable::because('GoldAPI response did not include a numeric price.');
        }

        try {
            $value = Price::fromDecimal((string) $price);
        } catch (InvalidArgumentException $e) {
            throw PriceUnavailable::because('GoldAPI returned an implausible price: '.$e->getMessage(), $e);
        }

        $timestamp = $response->json('timestamp');

        $observedAt = is_numeric($timestamp)
            ? CarbonImmutable::createFromTimestamp((int) $timestamp, 'UTC')
            : CarbonImmutable::now();

        return new PriceQuote($value, $observedAt, self::SOURCE);
    }
}
