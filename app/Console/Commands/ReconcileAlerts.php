<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Alerts\AlertStatus;
use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Index\InflightEntry;
use App\Alerts\IndexRebuilder;
use App\Jobs\DeliverPriceAlert;
use App\Models\PriceAlert;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Facades\Log;

/**
 * The safety net that runs every minute.
 *
 * The index is a projection that can drift (Redis restarted, an index write
 * failed after a commit) and deliveries can get lost (a watcher died between
 * popping and dispatching, a worker died mid-send). Each pass repairs what it
 * finds; every action it takes is idempotent because the delivery job's claim
 * decides who actually sends.
 */
#[Signature('alerts:reconcile {--rebuild : Rebuild the index from the database unconditionally}')]
#[Description('Repair index drift and re-drive deliveries that were lost or stuck')]
final class ReconcileAlerts extends Command
{
    private const int BATCH = 1000;

    public function handle(AlertIndex $index, IndexRebuilder $rebuilder, QueueFactory $queue): int
    {
        $rebuilt = $this->rebuildIfNeeded($index, $rebuilder);
        $requeued = $this->requeueLostDeliveries($index, $queue);
        $redriven = $this->redriveStuckDeliveries($queue);

        $summary = ['rebuilt' => $rebuilt, 'requeued' => $requeued, 'redriven' => $redriven];

        Log::info('price-alert.reconciled', $summary);

        $this->components->info(sprintf(
            'Reconciled: index %s, %d lost delivery(ies) requeued, %d stuck delivery(ies) re-driven.',
            $rebuilt === null ? 'consistent' : "rebuilt ({$rebuilt} alerts)",
            $requeued,
            $redriven,
        ));

        return self::SUCCESS;
    }

    /**
     * Rebuild when forced, when the index was never built (Redis restarted),
     * or when it holds fewer members than there are active alerts.
     */
    private function rebuildIfNeeded(AlertIndex $index, IndexRebuilder $rebuilder): ?int
    {
        $active = PriceAlert::query()->active()->count();
        $indexed = array_sum($index->sizes());

        $reason = match (true) {
            (bool) $this->option('rebuild') => 'requested',
            ! $index->isReady() => 'index not ready',
            $active > $indexed => sprintf('drift: %d active alerts, %d indexed', $active, $indexed),
            default => null,
        };

        if ($reason === null) {
            return null;
        }

        $count = $rebuilder->rebuild();

        Log::warning('price-alert.index.rebuild-triggered', ['reason' => $reason, 'entries' => $count]);

        return $count;
    }

    /**
     * An alert popped from the index long ago and still unacknowledged while
     * the queue is empty has no job left to deliver it. Dispatch it again.
     */
    private function requeueLostDeliveries(AlertIndex $index, QueueFactory $queue): int
    {
        $nowMs = (int) CarbonImmutable::now()->getPreciseTimestamp(3);
        $ttlMs = (int) config('gold.index.inflight_ttl_seconds') * 1000;

        $lost = $index->staleInflight($nowMs - $ttlMs, self::BATCH);

        if ($lost === [] || $queue->connection()->size(DeliverPriceAlert::QUEUE) > 0) {
            return 0;
        }

        $jobs = array_map(
            fn (InflightEntry $entry): DeliverPriceAlert => new DeliverPriceAlert(
                $entry->id,
                new PriceQuote($entry->price, CarbonImmutable::now(), 'reconcile'),
            ),
            $lost,
        );

        $queue->connection()->bulk($jobs, '', DeliverPriceAlert::QUEUE);
        $index->touchInflight($lost, $nowMs);

        return count($jobs);
    }

    /**
     * A row that has been "sending" for longer than the claim window belongs
     * to a worker that died. The job's claim re-admits such rows.
     */
    private function redriveStuckDeliveries(QueueFactory $queue): int
    {
        $staleBefore = now()->subSeconds((int) config('gold.delivery.stale_after_seconds'));
        $count = 0;

        PriceAlert::query()
            ->where('status', AlertStatus::Sending)
            ->where('updated_at', '<=', $staleBefore)
            ->lazyById(self::BATCH)
            ->each(function (PriceAlert $alert) use ($queue, &$count): void {
                $quote = new PriceQuote(
                    $alert->triggered_price ?? $alert->target_price,
                    $alert->triggered_at ?? CarbonImmutable::now(),
                    'reconcile',
                );

                $queue->connection()->push(new DeliverPriceAlert($alert->id, $quote), '', DeliverPriceAlert::QUEUE);
                $count++;
            });

        return $count;
    }
}
