<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Alerts\AlertStatus;
use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Direction;
use App\Jobs\DeliverPriceAlert;
use App\Models\PriceAlert;
use App\Models\User;
use App\Notifications\PriceAlertTriggered;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

final class DeliverPriceAlertTest extends TestCase
{
    use RefreshDatabase;

    private AlertIndex $index;

    protected function setUp(): void
    {
        parent::setUp();

        $this->index = $this->app->make(AlertIndex::class);
    }

    #[Test]
    public function it_emails_the_user_once_then_deletes_the_alert_and_acknowledges_the_index(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $alert = PriceAlert::factory()->for($user)->above('2700')->create();
        $quote = $this->popped($alert, '2701.25');

        $this->deliver(new DeliverPriceAlert($alert->id, $quote));

        Notification::assertSentTo($user, PriceAlertTriggered::class, function (PriceAlertTriggered $notification) use ($alert, $quote): bool {
            return $notification->alert->id === $alert->id
                && $notification->quote->price->equals($quote->price)
                && $notification->alert->status === AlertStatus::Sending
                && $notification->alert->triggered_price?->equals($quote->price) === true
                && $notification->alert->attempts === 1;
        });
        $this->assertDatabaseMissing('price_alerts', ['id' => $alert->id]);
        self::assertSame(0, $this->index->sizes()['inflight']);
    }

    #[Test]
    public function running_the_same_delivery_twice_sends_exactly_once(): void
    {
        Notification::fake();
        $alert = PriceAlert::factory()->above('2700')->create();
        $quote = $this->popped($alert, '2701.25');

        $this->deliver(new DeliverPriceAlert($alert->id, $quote));
        $this->deliver(new DeliverPriceAlert($alert->id, $quote));

        Notification::assertSentTimes(PriceAlertTriggered::class, 1);
        self::assertSame(0, $this->index->sizes()['inflight']);
    }

    #[Test]
    public function a_missing_alert_is_acknowledged_without_sending(): void
    {
        Notification::fake();
        $quote = $this->quote('2701.25');
        $this->index->add(999, Direction::Above, Price::fromDecimal('2700'));
        $this->index->pop($quote, 10);

        $this->deliver(new DeliverPriceAlert(999, $quote));

        Notification::assertNothingSent();
        self::assertSame(0, $this->index->sizes()['inflight']);
    }

    #[Test]
    public function a_failed_alert_is_acknowledged_without_sending(): void
    {
        Notification::fake();
        $alert = PriceAlert::factory()->failed()->above('2700')->create();
        $quote = $this->popped($alert, '2701.25');

        $this->deliver(new DeliverPriceAlert($alert->id, $quote));

        Notification::assertNothingSent();
        self::assertSame(AlertStatus::Failed, $alert->fresh()?->status);
        self::assertSame(0, $this->index->sizes()['inflight']);
    }

    #[Test]
    public function an_alert_the_price_does_not_hit_is_put_back_into_the_index(): void
    {
        Notification::fake();
        $alert = PriceAlert::factory()->above('2800')->create();
        $quote = $this->quote('2701.25');
        // A stale index entry claimed the alert was at 2600.
        $this->index->add($alert->id, Direction::Above, Price::fromDecimal('2600'));
        $this->index->pop($quote, 10);

        $this->deliver(new DeliverPriceAlert($alert->id, $quote));

        Notification::assertNothingSent();
        self::assertSame(AlertStatus::Active, $alert->fresh()?->status);
        self::assertSame(['above' => 1, 'below' => 0, 'inflight' => 0], $this->index->sizes());
        self::assertSame([$alert->id], $this->index->pop($this->quote('2800'), 10), 'it was re-indexed at its real target');
    }

    #[Test]
    public function it_neither_sends_nor_acknowledges_while_another_worker_owns_the_delivery(): void
    {
        Notification::fake();
        $alert = PriceAlert::factory()->sending()->above('2700')->create();
        $quote = $this->popped($alert, '2701.25');

        $this->deliver(new DeliverPriceAlert($alert->id, $quote));

        Notification::assertNothingSent();
        self::assertSame(AlertStatus::Sending, $alert->fresh()?->status);
        self::assertSame(1, $alert->fresh()?->attempts);
        self::assertSame(1, $this->index->sizes()['inflight'], 'the owner will acknowledge it');
    }

    #[Test]
    public function it_takes_over_a_delivery_that_was_abandoned(): void
    {
        Notification::fake();
        $alert = PriceAlert::factory()->sending()->above('2700')->create(['updated_at' => now()->subSeconds(121)]);
        $quote = $this->popped($alert, '2701.25');

        $this->deliver(new DeliverPriceAlert($alert->id, $quote));

        Notification::assertSentTimes(PriceAlertTriggered::class, 1);
        $this->assertDatabaseMissing('price_alerts', ['id' => $alert->id]);
        self::assertSame(0, $this->index->sizes()['inflight']);
    }

    #[Test]
    public function a_refused_message_releases_the_claim_and_rethrows(): void
    {
        $this->mock(MailChannel::class, function (MockInterface $mock): void {
            $mock->shouldReceive('send')->once()->andThrow(new TransportException('SMTP refused the message'));
        });
        $alert = PriceAlert::factory()->above('2700')->create();
        $quote = $this->popped($alert, '2701.25');

        try {
            $this->deliver(new DeliverPriceAlert($alert->id, $quote));
            self::fail('The transport exception should propagate so the queue retries the job.');
        } catch (TransportException $e) {
            self::assertSame('SMTP refused the message', $e->getMessage());
        }

        $fresh = $alert->fresh();

        self::assertSame(AlertStatus::Active, $fresh?->status);
        self::assertSame(1, $fresh->attempts);
        self::assertSame(DeliverPriceAlert::REASON_REFUSED, $fresh->last_error, 'the transport message stays in the log');
        self::assertSame(1, $this->index->sizes()['inflight'], 'still owed a delivery');
    }

    #[Test]
    public function any_failure_before_the_message_is_accepted_releases_the_claim(): void
    {
        $this->mock(MailChannel::class, function (MockInterface $mock): void {
            $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('The mail template is broken'));
        });
        $alert = PriceAlert::factory()->above('2700')->create();
        $quote = $this->popped($alert, '2701.25');

        try {
            $this->deliver(new DeliverPriceAlert($alert->id, $quote));
            self::fail('The exception should propagate so the queue retries the job.');
        } catch (RuntimeException $e) {
            self::assertSame('The mail template is broken', $e->getMessage());
        }

        self::assertSame(AlertStatus::Active, $alert->fresh()?->status, 'the next attempt can claim it again');
        self::assertSame(DeliverPriceAlert::REASON_NOT_SENT, $alert->fresh()?->last_error);
        self::assertSame(1, $this->index->sizes()['inflight']);
    }

    #[Test]
    public function a_row_that_vanished_after_being_loaded_is_acknowledged_without_sending(): void
    {
        Notification::fake();
        $alert = PriceAlert::factory()->above('2700')->create();
        $quote = $this->popped($alert, '2701.25');
        // The user cancels between the job loading the row and claiming it.
        PriceAlert::retrieved(function (PriceAlert $loaded) use ($alert): void {
            PriceAlert::query()->whereKey($alert->id)->delete();
        });

        $this->deliver(new DeliverPriceAlert($alert->id, $quote));

        Notification::assertNothingSent();
        self::assertSame(0, $this->index->sizes()['inflight']);
    }

    #[Test]
    public function giving_up_marks_the_alert_failed_and_acknowledges_the_index(): void
    {
        $alert = PriceAlert::factory()->sending()->above('2700')->create();
        $quote = $this->popped($alert, '2701.25');

        (new DeliverPriceAlert($alert->id, $quote))->failed(new RuntimeException('boom'));

        $fresh = $alert->fresh();

        self::assertSame(AlertStatus::Failed, $fresh?->status);
        self::assertSame(DeliverPriceAlert::REASON_GAVE_UP, $fresh->last_error, 'the exception message is logged, not shown');
        self::assertSame(0, $this->index->sizes()['inflight']);
    }

    #[Test]
    public function it_is_dispatched_to_the_dedicated_alerts_queue(): void
    {
        Queue::fake();

        DeliverPriceAlert::dispatch(1, $this->quote('2700'));

        Queue::assertPushedOn(DeliverPriceAlert::QUEUE, DeliverPriceAlert::class);
    }

    private function deliver(DeliverPriceAlert $job): void
    {
        $this->app->call($job->handle(...));
    }

    private function quote(string $price): PriceQuote
    {
        return new PriceQuote(Price::fromDecimal($price), CarbonImmutable::now(), 'test');
    }

    /** Index the alert and pop it at the given price, as the watcher would. */
    private function popped(PriceAlert $alert, string $price): PriceQuote
    {
        $quote = $this->quote($price);

        $this->index->add($alert->id, $alert->direction, $alert->target_price);
        $popped = $this->index->pop($quote, 10);

        self::assertSame([$alert->id], $popped);

        return $quote;
    }
}
