<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Alerts\AlertMatcher;
use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Direction;
use App\Alerts\Exceptions\IndexUnavailable;
use App\Alerts\Index\InMemoryAlertIndex;
use App\Alerts\IndexRebuilder;
use App\Jobs\DeliverPriceAlert;
use App\Models\PriceAlert;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AlertMatcherTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryAlertIndex $index;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $index = $this->app->make(AlertIndex::class);
        self::assertInstanceOf(InMemoryAlertIndex::class, $index);
        $this->index = $index;
    }

    #[Test]
    public function a_gap_over_several_targets_fires_each_of_them_once(): void
    {
        $this->index->add(1, Direction::Above, Price::fromDecimal('2700'));
        $this->index->add(2, Direction::Above, Price::fromDecimal('2750'));
        $this->index->add(3, Direction::Above, Price::fromDecimal('2800'));

        self::assertSame(0, $this->matcher()->match($this->quote('2600'))->matched);
        self::assertSame(3, $this->matcher()->match($this->quote('2810'))->matched);
        self::assertSame(0, $this->matcher()->match($this->quote('2820'))->matched);

        Queue::assertPushed(DeliverPriceAlert::class, 3);
        self::assertSame([1, 2, 3], $this->dispatchedIds());
    }

    #[Test]
    public function a_price_bouncing_around_a_target_fires_it_once(): void
    {
        $this->index->add(1, Direction::Above, Price::fromDecimal('2700'));

        self::assertSame(1, $this->matcher()->match($this->quote('2701'))->matched);
        self::assertSame(0, $this->matcher()->match($this->quote('2699'))->matched);
        self::assertSame(0, $this->matcher()->match($this->quote('2702'))->matched);

        Queue::assertPushed(DeliverPriceAlert::class, 1);
    }

    #[Test]
    public function each_side_only_fires_in_its_own_direction(): void
    {
        $this->index->add(1, Direction::Below, Price::fromDecimal('2600'));
        $this->index->add(2, Direction::Above, Price::fromDecimal('2700'));

        self::assertSame(0, $this->matcher()->match($this->quote('2650'))->matched);
        self::assertSame(1, $this->matcher()->match($this->quote('2600'))->matched);

        self::assertSame([1], $this->dispatchedIds());
    }

    #[Test]
    public function reaching_the_exact_target_counts(): void
    {
        $this->index->add(1, Direction::Above, Price::fromDecimal('2700'));

        self::assertSame(1, $this->matcher()->match($this->quote('2700'))->matched);
    }

    #[Test]
    public function it_rebuilds_the_index_from_the_database_when_it_is_not_ready(): void
    {
        $this->index->markNotReady();
        $hit = PriceAlert::factory()->above('2700')->create();
        PriceAlert::factory()->above('2900')->create();
        PriceAlert::factory()->below('2500')->create();
        PriceAlert::factory()->failed()->above('2600')->create();
        PriceAlert::factory()->sending()->above('2650')->create();

        $outcome = $this->matcher()->match($this->quote('2750'));

        self::assertTrue($outcome->rebuilt);
        self::assertSame(1, $outcome->matched);
        self::assertTrue($this->index->isReady());
        self::assertSame(['above' => 1, 'below' => 1, 'inflight' => 1], $this->index->sizes());
        self::assertSame([$hit->id], $this->dispatchedIds());
    }

    #[Test]
    public function it_gives_up_when_the_index_cannot_be_made_ready(): void
    {
        $this->index->markNotReady();
        $this->mock(IndexRebuilder::class, function (MockInterface $mock): void {
            $mock->shouldReceive('rebuild')->once()->andReturn(0);
        });

        $this->expectException(IndexUnavailable::class);

        $this->matcher()->match($this->quote('2700'));
    }

    #[Test]
    public function it_drains_the_index_in_batches_and_dispatches_in_chunks(): void
    {
        config()->set('gold.index.pop_batch', 2);
        config()->set('gold.index.dispatch_batch', 2);

        foreach (range(1, 5) as $id) {
            $this->index->add($id, Direction::Above, Price::fromDecimal('2700'));
        }

        $outcome = $this->matcher()->match($this->quote('2700'));

        self::assertSame(5, $outcome->matched);
        self::assertSame(3, $outcome->batches);
        self::assertSame([1, 2, 3, 4, 5], $this->dispatchedIds());
    }

    #[Test]
    public function the_pop_batch_is_clamped_to_what_the_script_can_handle(): void
    {
        config()->set('gold.index.pop_batch', 5000);

        foreach (range(1, AlertMatcher::MAX_POP_BATCH + 1) as $id) {
            $this->index->add($id, Direction::Above, Price::fromDecimal('2700'));
        }

        $outcome = $this->matcher()->match($this->quote('2700'));

        self::assertSame(AlertMatcher::MAX_POP_BATCH + 1, $outcome->matched);
        self::assertSame(2, $outcome->batches);
    }

    #[Test]
    public function jobs_carry_the_quote_and_go_to_the_alerts_queue(): void
    {
        $this->index->add(1, Direction::Above, Price::fromDecimal('2700'));
        $quote = $this->quote('2701.25');

        $this->matcher()->match($quote);

        Queue::assertPushedOn('alerts', DeliverPriceAlert::class, fn (DeliverPriceAlert $job): bool => $job->alertId === 1
            && $job->quote->price->equals($quote->price)
            && $job->quote->source === $quote->source);
    }

    #[Test]
    public function nothing_is_dispatched_when_nothing_is_hit(): void
    {
        $this->index->add(1, Direction::Above, Price::fromDecimal('2700'));

        $outcome = $this->matcher()->match($this->quote('2600'));

        self::assertSame(0, $outcome->matched);
        self::assertSame(1, $outcome->batches);
        self::assertFalse($outcome->rebuilt);
        Queue::assertNothingPushed();
        self::assertSame('2600.0000', $this->index->currentPrice()?->quote->price->toDecimal(), 'the tick is still recorded');
    }

    private function matcher(): AlertMatcher
    {
        return $this->app->make(AlertMatcher::class);
    }

    private function quote(string $price): PriceQuote
    {
        return new PriceQuote(Price::fromDecimal($price), CarbonImmutable::now(), 'test');
    }

    /**
     * @return list<int>
     */
    private function dispatchedIds(): array
    {
        $ids = Queue::pushed(DeliverPriceAlert::class)->map(fn (DeliverPriceAlert $job): int => $job->alertId)->all();
        sort($ids);

        return $ids;
    }
}
