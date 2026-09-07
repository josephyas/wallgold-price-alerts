<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Alerts\AlertMatcher;
use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Direction;
use App\Jobs\DeliverPriceAlert;
use App\Pricing\Contracts\PriceProvider;
use App\Pricing\Exceptions\PriceUnavailable;
use App\Pricing\Price;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\SequencePriceProvider;
use Tests\TestCase;

final class WatchPriceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Sleep::fake();
        config()->set('gold.poll_interval_ms', 1000);
    }

    #[Test]
    public function a_single_tick_dispatches_the_alerts_the_price_crosses(): void
    {
        $this->app->instance(PriceProvider::class, new SequencePriceProvider(['2600', '2710']));
        $this->app->make(AlertIndex::class)->add(7, Direction::Above, Price::fromDecimal('2700'));

        $this->artisan('price:watch --once')->assertSuccessful();
        Queue::assertNothingPushed();

        $this->artisan('price:watch --once')->assertSuccessful();
        Queue::assertPushedOn('alerts', DeliverPriceAlert::class, fn (DeliverPriceAlert $job): bool => $job->alertId === 7
            && $job->quote->price->toDecimal() === '2710.0000');

        Sleep::assertNeverSlept();
    }

    #[Test]
    public function a_failing_feed_makes_a_single_tick_fail(): void
    {
        $this->app->instance(PriceProvider::class, new SequencePriceProvider([PriceUnavailable::because('feed down')]));

        $this->artisan('price:watch --once')
            ->expectsOutputToContain('feed down')
            ->assertFailed();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_loop_backs_off_after_a_failure_and_keeps_going(): void
    {
        $this->app->instance(PriceProvider::class, new SequencePriceProvider([
            PriceUnavailable::because('feed down'),
            '2600',
            '2610',
        ]));

        $this->artisan('price:watch --max-ticks=3')->assertSuccessful();

        Sleep::assertSleptTimes(2);
        Sleep::assertSlept(fn (CarbonInterval $duration): bool => (int) $duration->totalMilliseconds === 2000, times: 1);
        Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalMilliseconds > 900 && $duration->totalMilliseconds <= 1000, times: 1);
    }

    #[Test]
    public function a_failure_inside_matching_does_not_kill_the_loop(): void
    {
        $this->app->instance(PriceProvider::class, new SequencePriceProvider(['2600', '2601']));
        $this->mock(AlertMatcher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('match')->once()->andThrow(new RuntimeException('redis is away'));
            $mock->shouldReceive('match')->once()->andReturn(new \App\Alerts\MatchOutcome(0, 1, false));
        });

        $this->artisan('price:watch --max-ticks=2')
            ->expectsOutputToContain('redis is away')
            ->assertSuccessful();
    }
}
