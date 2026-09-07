<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Direction;
use App\Alerts\Index\RedisAlertIndex;
use App\Models\PriceAlert;
use App\Pricing\Price;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\UsesRedis;
use Tests\TestCase;

#[Group('redis')]
final class ReconcileAlertsRedisTest extends TestCase
{
    use RefreshDatabase;
    use UsesRedis;

    #[Test]
    public function it_rebuilds_the_real_index_after_a_redis_restart_and_purges_phantoms(): void
    {
        Queue::fake();
        $index = new RedisAlertIndex($this->app->make(RedisFactory::class), (string) config('gold.redis_connection'));
        $this->app->instance(AlertIndex::class, $index);

        $alert = PriceAlert::factory()->above('2700')->create();
        $index->add(999, Direction::Above, Price::fromDecimal('1'));

        self::assertFalse($index->isReady(), 'a fresh Redis has no ready flag');

        $this->artisan('alerts:reconcile')->expectsOutputToContain('index rebuilt (1 alerts)')->assertSuccessful();

        self::assertTrue($index->isReady());
        self::assertSame(['above' => 1, 'below' => 0, 'inflight' => 0], $index->sizes());
        self::assertSame(27_000_000.0, $this->redis()->zscore(RedisAlertIndex::KEY_ABOVE, (string) $alert->id));
        self::assertFalse($this->redis()->zscore(RedisAlertIndex::KEY_ABOVE, '999'));
    }
}
