<?php

declare(strict_types=1);

namespace App\Alerts;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Exceptions\IndexUnavailable;
use App\Jobs\DeliverPriceAlert;
use App\Pricing\PriceQuote;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Facades\Log;

/**
 * Turns one price tick into delivery jobs.
 *
 * Every tick is matched, even when the price has not moved: an alert that was
 * re-indexed at exactly the current level still has to fire.
 */
class AlertMatcher
{
    /**
     * The pop script passes twice this many arguments to ZADD through Lua's
     * unpack(), whose stack allows about 8000 slots; 1000 keeps a wide margin.
     */
    public const int MAX_POP_BATCH = 1000;

    public function __construct(
        private readonly AlertIndex $index,
        private readonly IndexRebuilder $rebuilder,
        private readonly QueueFactory $queue,
        private readonly Config $config,
    ) {}

    /**
     * @throws IndexUnavailable when the index cannot be made ready
     */
    public function match(PriceQuote $quote): MatchOutcome
    {
        $limit = min(self::MAX_POP_BATCH, max(1, (int) $this->config->get('gold.index.pop_batch')));
        $ids = [];
        $batches = 0;
        $rebuilt = false;

        while (true) {
            $batch = $this->index->pop($quote, $limit);

            if ($batch === null) {
                if ($rebuilt) {
                    throw new IndexUnavailable('The alert index is not ready even after a rebuild.');
                }

                $this->rebuilder->rebuild();
                $rebuilt = true;

                continue;
            }

            $batches++;
            array_push($ids, ...$batch);

            if (count($batch) < $limit) {
                break;
            }
        }

        $this->dispatch($ids, $quote);

        if ($ids !== []) {
            Log::info('price-alert.matched', [
                'price' => $quote->price->toDecimal(),
                'source' => $quote->source,
                'matched' => count($ids),
                'batches' => $batches,
            ]);
        }

        return new MatchOutcome(count($ids), $batches, $rebuilt);
    }

    /**
     * @param  list<int>  $ids
     */
    private function dispatch(array $ids, PriceQuote $quote): void
    {
        $chunkSize = max(1, (int) $this->config->get('gold.index.dispatch_batch'));

        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            $jobs = array_map(fn (int $id): DeliverPriceAlert => new DeliverPriceAlert($id, $quote), $chunk);

            // One pipelined round trip per chunk; the sync driver runs them inline.
            $this->queue->connection()->bulk($jobs, '', DeliverPriceAlert::QUEUE);
        }
    }
}
