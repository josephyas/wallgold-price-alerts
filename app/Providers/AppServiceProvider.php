<?php

declare(strict_types=1);

namespace App\Providers;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Index\RedisAlertIndex;
use App\Pricing\Contracts\PriceProvider;
use App\Pricing\Contracts\ScriptedPrices;
use App\Pricing\Price;
use App\Pricing\Providers\FakePriceProvider;
use App\Pricing\Providers\GoldApiPriceProvider;
use App\Pricing\Providers\RedisScriptedPrices;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AlertIndex::class, function (Application $app): AlertIndex {
            /** @var Config $config */
            $config = $app->make(Config::class);

            return new RedisAlertIndex($app->make(RedisFactory::class), (string) $config->get('gold.redis_connection'));
        });

        $this->app->singleton(ScriptedPrices::class, function (Application $app): ScriptedPrices {
            /** @var Config $config */
            $config = $app->make(Config::class);

            return new RedisScriptedPrices($app->make(RedisFactory::class), (string) $config->get('gold.redis_connection'));
        });

        $this->app->singleton(PriceProvider::class, function (Application $app): PriceProvider {
            /** @var Config $config */
            $config = $app->make(Config::class);
            $provider = (string) $config->get('gold.provider');

            return match ($provider) {
                'fake' => new FakePriceProvider(
                    start: Price::fromDecimal((string) $config->get('gold.fake.start')),
                    maxStep: Price::fromDecimal((string) $config->get('gold.fake.max_step')),
                    seed: is_numeric($seed = $config->get('gold.fake.seed')) ? (int) $seed : null,
                    script: $app->make(ScriptedPrices::class),
                ),
                'goldapi' => new GoldApiPriceProvider(
                    http: $app->make(Http::class),
                    url: (string) $config->get('gold.goldapi.url'),
                    token: (string) $config->get('gold.goldapi.token'),
                    timeout: (float) $config->get('gold.goldapi.timeout'),
                ),
                default => throw new InvalidArgumentException(sprintf('Unknown gold price provider [%s].', $provider)),
            };
        });
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute((int) config('gold.api_rate_per_minute'))
            ->by((string) ($request->user('sanctum')?->getAuthIdentifier() ?? $request->ip())));
    }
}
