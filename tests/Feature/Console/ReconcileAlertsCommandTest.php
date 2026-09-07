<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Direction;
use App\Alerts\Index\InMemoryAlertIndex;
use App\Alerts\IndexRebuilder;
use App\Jobs\DeliverPriceAlert;
use App\Models\PriceAlert;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReconcileAlertsCommandTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryAlertIndex $index;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        CarbonImmutable::setTestNow('2026-09-07 12:00:00');

        $index = $this->app->make(AlertIndex::class);
        self::assertInstanceOf(InMemoryAlertIndex::class, $index);
        $this->index = $index;
    }

    #[Test]
    public function a_consistent_system_is_left_alone(): void
    {
        $alert = PriceAlert::factory()->above('2700')->create();
        $this->index->add($alert->id, Direction::Above, Price::fromDecimal('2700'));
        $this->spy(IndexRebuilder::class);

        $this->artisan('alerts:reconcile')
            ->expectsOutputToContain('index consistent, 0 lost delivery(ies) requeued, 0 stuck delivery(ies) re-driven')
            ->assertSuccessful();

        $this->spy(IndexRebuilder::class)->shouldNotHaveReceived('rebuild');
        Queue::assertNothingPushed();
    }

    #[Test]
    public function an_index_that_was_never_built_is_rebuilt(): void
    {
        $this->index->markNotReady();
        PriceAlert::factory()->above('2700')->create();

        $this->artisan('alerts:reconcile')->expectsOutputToContain('index rebuilt (1 alerts)')->assertSuccessful();

        self::assertTrue($this->index->isReady());
        self::assertSame(['above' => 1, 'below' => 0, 'inflight' => 0], $this->index->sizes());
    }

    #[Test]
    public function drift_between_the_database_and_the_index_is_repaired(): void
    {
        PriceAlert::factory()->above('2700')->create();
        PriceAlert::factory()->below('2600')->create();
        // Only one of the two made it into the index (Redis was down when the other was created).
        $this->index->add(1, Direction::Above, Price::fromDecimal('2700'));

        $this->artisan('alerts:reconcile')->expectsOutputToContain('index rebuilt (2 alerts)')->assertSuccessful();

        self::assertSame(['above' => 1, 'below' => 1, 'inflight' => 0], $this->index->sizes());
    }

    #[Test]
    public function a_rebuild_can_be_forced(): void
    {
        $this->index->add(999, Direction::Above, Price::fromDecimal('1'));

        $this->artisan('alerts:reconcile --rebuild')->expectsOutputToContain('index rebuilt (0 alerts)')->assertSuccessful();

        self::assertSame(['above' => 0, 'below' => 0, 'inflight' => 0], $this->index->sizes(), 'the phantom member is gone');
    }

    #[Test]
    public function a_delivery_lost_after_being_popped_is_dispatched_again_when_the_queue_is_idle(): void
    {
        $alert = PriceAlert::factory()->above('2700')->create();
        $this->index->add($alert->id, Direction::Above, Price::fromDecimal('2700'));
        $this->index->pop($this->quote('2701.25'), 10);

        $this->artisan('alerts:reconcile')->expectsOutputToContain('0 lost delivery(ies) requeued')->assertSuccessful();
        Queue::assertNothingPushed();

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(601));

        $this->artisan('alerts:reconcile')->expectsOutputToContain('1 lost delivery(ies) requeued')->assertSuccessful();

        Queue::assertPushedOn('alerts', DeliverPriceAlert::class, fn (DeliverPriceAlert $job): bool => $job->alertId === $alert->id
            && $job->quote->price->toDecimal() === '2701.2500'
            && $job->quote->source === 'reconcile');

        // Touched: it will not be reported again until the window passes once more.
        $this->artisan('alerts:reconcile')->expectsOutputToContain('0 lost delivery(ies) requeued')->assertSuccessful();
        Queue::assertPushed(DeliverPriceAlert::class, 1);
    }

    #[Test]
    public function lost_deliveries_are_not_requeued_while_the_queue_still_has_work(): void
    {
        $alert = PriceAlert::factory()->above('2700')->create();
        $this->index->add($alert->id, Direction::Above, Price::fromDecimal('2700'));
        $this->index->pop($this->quote('2701.25'), 10);
        DeliverPriceAlert::dispatch(12345, $this->quote('2701.25'));
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(601));

        $this->artisan('alerts:reconcile')->expectsOutputToContain('0 lost delivery(ies) requeued')->assertSuccessful();

        Queue::assertPushed(DeliverPriceAlert::class, 1);
    }

    #[Test]
    public function a_delivery_stuck_in_sending_is_driven_again(): void
    {
        $fresh = PriceAlert::factory()->sending()->above('2700')->create([
            'triggered_price' => '2701.25',
            'triggered_at' => CarbonImmutable::parse('2026-09-07 11:59:00'),
        ]);
        $stuck = PriceAlert::factory()->sending()->above('2650')->create([
            'triggered_price' => '2651.5',
            'triggered_at' => CarbonImmutable::parse('2026-09-07 11:50:00'),
            'updated_at' => CarbonImmutable::now()->subSeconds(121),
        ]);

        $this->artisan('alerts:reconcile')->expectsOutputToContain('1 stuck delivery(ies) re-driven')->assertSuccessful();

        Queue::assertPushed(DeliverPriceAlert::class, 1);
        Queue::assertPushedOn('alerts', DeliverPriceAlert::class, fn (DeliverPriceAlert $job): bool => $job->alertId === $stuck->id
            && $job->quote->price->toDecimal() === '2651.5000'
            && $job->quote->observedAt->equalTo(CarbonImmutable::parse('2026-09-07 11:50:00'))
            && $job->quote->source === 'reconcile');
        Queue::assertNotPushed(DeliverPriceAlert::class, fn (DeliverPriceAlert $job): bool => $job->alertId === $fresh->id);
    }

    #[Test]
    public function it_runs_every_minute_on_one_server(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('alerts:reconcile')->assertSuccessful();

        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, 'alerts:reconcile'));

        self::assertInstanceOf(Event::class, $event);
        self::assertSame('* * * * *', $event->expression);
        self::assertTrue($event->withoutOverlapping);
        self::assertTrue($event->onOneServer);
    }

    private function quote(string $price): PriceQuote
    {
        return new PriceQuote(Price::fromDecimal($price), CarbonImmutable::now(), 'test');
    }
}
