<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Alerts\AlertStatus;
use App\Alerts\Contracts\AlertIndex;
use App\Models\PriceAlert;
use App\Notifications\PriceAlertTriggered;
use App\Pricing\PriceQuote;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Email one triggered alert exactly once, then delete it.
 *
 * The delivery is claimed with a single conditional UPDATE (active -> sending),
 * which is atomic on every supported database. Two jobs for the same alert,
 * whether from a retry, a reconcile re-dispatch or a second watcher, cannot
 * both win the claim, so the email is sent once. The claim is taken before
 * the send and the row is deleted after the mail server accepts the message.
 */
#[Queue(DeliverPriceAlert::QUEUE)]
#[Tries(3)]
#[Backoff(5, 30, 120)]
#[Timeout(60)]
final class DeliverPriceAlert implements ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'alerts';

    public function __construct(
        public readonly int $alertId,
        public readonly PriceQuote $quote,
    ) {}

    public function handle(AlertIndex $index, Config $config): void
    {
        $alert = PriceAlert::query()->with('user')->find($this->alertId);

        if (! $alert instanceof PriceAlert || $alert->status === AlertStatus::Failed) {
            // Nothing left to deliver: cancelled, already delivered, or given up on.
            $index->ack($this->alertId, $this->quote->price);

            return;
        }

        if (! $alert->isHitBy($this->quote->price)) {
            // The index entry was stale (for example rebuilt while this alert was in flight). Put it back.
            $index->add($alert->id, $alert->direction, $alert->target_price);
            $index->ack($alert->id, $this->quote->price);

            Log::warning('price-alert.not-hit', ['alert_id' => $alert->id, 'price' => $this->quote->price->toDecimal()]);

            return;
        }

        if (! $this->claim($alert, (int) $config->get('gold.delivery.stale_after_seconds'))) {
            if (! PriceAlert::query()->whereKey($alert->id)->exists()) {
                // The row vanished between loading and claiming (cancelled, or already
                // delivered by another worker): nothing is owed any more.
                $index->ack($alert->id, $this->quote->price);

                return;
            }

            // Another worker owns this delivery right now; it will acknowledge the index when done.
            Log::info('price-alert.claim-lost', ['alert_id' => $alert->id]);

            return;
        }

        try {
            $alert->refresh();
            $alert->user->notifyNow(new PriceAlertTriggered($alert, $this->quote));
        } catch (Throwable $e) {
            // Nothing runs after the transport accepts the message (there are no
            // NotificationSent listeners), so any exception here means it was not
            // sent: hand the claim back and let the queue retry with backoff.
            $this->releaseClaim($alert, $e);

            throw $e;
        }

        retry(3, fn () => $alert->delete(), 100);

        $index->ack($alert->id, $this->quote->price);

        Log::info('price-alert.delivered', [
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'target_price' => $alert->target_price->toDecimal(),
            'price' => $this->quote->price->toDecimal(),
            'observed_at' => $this->quote->observedAt->toIso8601String(),
        ]);
    }

    /**
     * Every attempt failed: keep the row so the user can see it, and stop tracking it in the index.
     */
    public function failed(?Throwable $exception): void
    {
        PriceAlert::query()
            ->whereKey($this->alertId)
            ->whereIn('status', [AlertStatus::Active, AlertStatus::Sending])
            ->update([
                'status' => AlertStatus::Failed,
                'last_error' => mb_substr((string) $exception?->getMessage(), 0, 1000),
            ]);

        app(AlertIndex::class)->ack($this->alertId, $this->quote->price);

        Log::error('price-alert.failed', ['alert_id' => $this->alertId, 'error' => $exception?->getMessage()]);
    }

    /**
     * Take ownership of the delivery. Only an active alert, or one whose
     * previous owner has been silent for too long, can be claimed.
     */
    private function claim(PriceAlert $alert, int $staleAfterSeconds): bool
    {
        $updated = PriceAlert::query()
            ->whereKey($alert->id)
            ->where(fn (Builder $query) => $query
                ->where('status', AlertStatus::Active)
                ->orWhere(fn (Builder $query) => $query
                    ->where('status', AlertStatus::Sending)
                    ->where('updated_at', '<=', now()->subSeconds($staleAfterSeconds))))
            ->update([
                'status' => AlertStatus::Sending,
                'triggered_price' => $this->quote->price->minor,
                'triggered_at' => $this->quote->observedAt,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        return $updated === 1;
    }

    private function releaseClaim(PriceAlert $alert, Throwable $reason): void
    {
        PriceAlert::query()
            ->whereKey($alert->id)
            ->where('status', AlertStatus::Sending)
            ->update([
                'status' => AlertStatus::Active,
                'last_error' => mb_substr($reason->getMessage(), 0, 1000),
            ]);
    }
}
