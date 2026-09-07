<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Direction;
use App\Alerts\Index\IndexEntry;
use App\Alerts\Index\RedisAlertIndex;
use App\Pricing\Price;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\UsesRedis;

#[Group('redis')]
final class RedisAlertIndexTest extends AlertIndexContractTestCase
{
    use UsesRedis;

    protected function makeIndex(): AlertIndex
    {
        return new RedisAlertIndex($this->app->make(RedisFactory::class), (string) config('gold.redis_connection'));
    }

    #[Test]
    public function it_stores_targets_as_integer_scores_under_the_configured_prefix(): void
    {
        $this->index->rebuild([new IndexEntry(42, Direction::Above, Price::fromDecimal('2700.1234'))]);

        self::assertSame(27_001_234.0, $this->redis()->zscore(RedisAlertIndex::KEY_ABOVE, '42'));
        self::assertSame(['alerts:above', 'alerts:ready'], collect($this->redis()->keys('alerts:*'))
            ->map(fn (string $key): string => str_replace((string) config('database.redis.options.prefix'), '', $key))
            ->sort()
            ->values()
            ->all());
    }

    #[Test]
    public function the_container_resolves_the_redis_index_as_a_singleton(): void
    {
        // The test base class swaps in the in-memory index; restore the real binding.
        (new AppServiceProvider($this->app))->register();
        $this->app->forgetInstance(AlertIndex::class);

        $index = $this->app->make(AlertIndex::class);

        self::assertInstanceOf(RedisAlertIndex::class, $index);
        self::assertSame($index, $this->app->make(AlertIndex::class));
    }
}
