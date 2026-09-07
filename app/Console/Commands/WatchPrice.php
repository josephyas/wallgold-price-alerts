<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Alerts\AlertMatcher;
use App\Pricing\Contracts\PriceProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * The long-running process that turns price ticks into alert deliveries.
 *
 * A dedicated loop rather than the scheduler: it polls at a configurable
 * sub-second interval, keeps the framework booted between ticks, and backs off
 * when the feed or the index misbehaves instead of dying.
 */
#[Signature('price:watch
    {--once : Run a single tick and exit}
    {--max-ticks= : Stop after this many ticks}')]
#[Description('Poll the gold price and dispatch the alerts each tick triggers')]
final class WatchPrice extends Command
{
    private const int MAX_BACKOFF_MS = 10_000;

    private const int MIN_INTERVAL_MS = 100;

    private bool $stopping = false;

    public function handle(PriceProvider $provider, AlertMatcher $matcher): int
    {
        if (extension_loaded('pcntl')) {
            $this->trap([SIGTERM, SIGINT], function (): void {
                $this->stopping = true;
            });
        }

        $interval = max(self::MIN_INTERVAL_MS, (int) config('gold.poll_interval_ms'));
        $once = (bool) $this->option('once');
        $maxTicks = is_numeric($this->option('max-ticks')) ? (int) $this->option('max-ticks') : null;
        $ticks = 0;
        $failures = 0;

        $this->components->info(sprintf('Watching the gold price every %d ms (%s).', $interval, $provider::class));

        while (! $this->stopping) {
            $started = hrtime(true);
            $ticks++;

            try {
                $quote = $provider->fetch();
                $outcome = $matcher->match($quote);
                $failures = 0;

                $this->line(sprintf(
                    '%s price=%s source=%s matched=%d batches=%d%s',
                    now()->format('H:i:s.v'),
                    $quote->price->toDecimal(),
                    $quote->source,
                    $outcome->matched,
                    $outcome->batches,
                    $outcome->rebuilt ? ' (index rebuilt)' : '',
                ), verbosity: 'v');
            } catch (Throwable $e) {
                report($e);
                $this->components->warn(sprintf('Tick failed: %s', $e->getMessage()));

                if ($once) {
                    return self::FAILURE;
                }

                if ($maxTicks !== null && $ticks >= $maxTicks) {
                    break;
                }

                $failures++;
                Sleep::for(min($interval * 2 ** min($failures, 6), self::MAX_BACKOFF_MS))->milliseconds();

                continue;
            }

            if ($once || ($maxTicks !== null && $ticks >= $maxTicks)) {
                break;
            }

            $elapsedMs = (hrtime(true) - $started) / 1_000_000;
            Sleep::for(max(0, (int) round($interval - $elapsedMs)))->milliseconds();
        }

        return self::SUCCESS;
    }
}
