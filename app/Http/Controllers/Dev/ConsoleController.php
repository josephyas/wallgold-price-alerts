<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dev;

use App\Alerts\Actions\CancelPriceAlert;
use App\Alerts\Actions\CreatePriceAlert;
use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Direction;
use App\Alerts\IndexRebuilder;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverPriceAlert;
use App\Models\PriceAlert;
use App\Models\User;
use App\Pricing\Contracts\ScriptedPrices;
use App\Pricing\Price;
use App\Pricing\Rules\ValidPrice;
use App\Support\MailpitInbox;
use Illuminate\Contracts\Queue\ClearableQueue;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Backs the development console: one snapshot endpoint the page polls, and a
 * few actions that drive the pipeline by hand. Guarded by DevConsoleOnly, so
 * none of it exists in production.
 */
final class ConsoleController extends Controller
{
    public function page(): View
    {
        return view('dev.console', [
            // The page lives at /dev, so relative request paths would resolve
            // against the site root; hand the script an explicit base.
            'base' => rtrim(route('dev.console'), '/').'/',
            'pollIntervalMs' => (int) config('gold.poll_interval_ms'),
            'unit' => (string) config('gold.unit'),
        ]);
    }

    /**
     * One snapshot of everything the console shows. Each section degrades on
     * its own, so a Redis or Mailpit outage greys out one card instead of
     * breaking the page.
     */
    public function state(AlertIndex $index, QueueFactory $queue, MailpitInbox $inbox): JsonResponse
    {
        return response()->json([
            'now' => now()->toIso8601String(),
            'price' => $this->priceState($index),
            'index' => $this->indexState($index),
            'queue' => $this->queueState($queue),
            'alerts' => $this->alertsState(),
            'inbox' => $inbox->recent(),
        ]);
    }

    /**
     * Hand the fake feed the next prices it will serve.
     */
    public function pushPrices(Request $request, ScriptedPrices $script): JsonResponse
    {
        $data = $request->validate([
            'prices' => ['required', 'array', 'min:1', 'max:50'],
            'prices.*' => ['required', new ValidPrice],
        ]);

        /** @var list<string> $values */
        $values = array_map(strval(...), $data['prices']);
        $prices = array_map(Price::fromDecimal(...), $values);

        $script->push(...$prices);

        return response()->json([
            'pushed' => array_map(fn (Price $price): string => $price->toDecimal(), $prices),
        ], Response::HTTP_ACCEPTED);
    }

    public function createAlert(Request $request, CreatePriceAlert $action): JsonResponse
    {
        $data = $request->validate([
            'target_price' => ['required', new ValidPrice],
            'direction' => ['nullable', 'string', Rule::enum(Direction::class)],
        ]);

        $direction = is_string($data['direction'] ?? null) ? Direction::from($data['direction']) : null;

        $alert = $action->create($this->consoleUser(), Price::fromDecimal((string) $data['target_price']), $direction);

        return response()->json(['alert' => $this->alert($alert)], Response::HTTP_CREATED);
    }

    public function deleteAlert(PriceAlert $alert, CancelPriceAlert $action): Response
    {
        $action->cancel($alert);

        return response()->noContent();
    }

    /**
     * Start from nothing: every alert, queued job, failed job, index entry,
     * scripted price and delivered mail. The recorded market price is left
     * alone, because it belongs to the feed rather than to anything the
     * console created.
     */
    public function reset(
        AlertIndex $index,
        IndexRebuilder $rebuilder,
        QueueFactory $queue,
        ScriptedPrices $script,
        MailpitInbox $inbox,
    ): JsonResponse {
        $alerts = PriceAlert::query()->count();
        PriceAlert::query()->delete();

        $failed = DB::table('failed_jobs')->delete();

        $connection = $queue->connection();
        $queued = $connection instanceof ClearableQueue
            ? $connection->clear(DeliverPriceAlert::QUEUE)
            : 0;

        try {
            $script->clear();
            $index->purge();
            $rebuilder->rebuild();
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json([
            'alerts' => $alerts,
            'queued' => $queued,
            'failed' => $failed,
            'mail' => $inbox->clear(),
        ]);
    }

    public function clearInbox(MailpitInbox $inbox): JsonResponse
    {
        return response()->json(['cleared' => $inbox->clear()]);
    }

    /**
     * @return array{available: bool, price: string|null, unit: string, source: string|null, observed_at: string|null, received_at: string|null, stale: bool}
     */
    private function priceState(AlertIndex $index): array
    {
        $unit = (string) config('gold.unit');

        try {
            $current = $index->currentPrice();
        } catch (Throwable) {
            return ['available' => false, 'price' => null, 'unit' => $unit, 'source' => null, 'observed_at' => null, 'received_at' => null, 'stale' => true];
        }

        if ($current === null) {
            return ['available' => true, 'price' => null, 'unit' => $unit, 'source' => null, 'observed_at' => null, 'received_at' => null, 'stale' => true];
        }

        return [
            'available' => true,
            'price' => $current->quote->price->toDecimal(),
            'unit' => $unit,
            'source' => $current->quote->source,
            'observed_at' => $current->quote->observedAt->toIso8601String(),
            'received_at' => $current->receivedAt->toIso8601String(),
            'stale' => $current->isStale((int) config('gold.price_max_age_ms')),
        ];
    }

    /**
     * @return array{available: bool, ready: bool, above: int, below: int, inflight: int}
     */
    private function indexState(AlertIndex $index): array
    {
        try {
            $sizes = $index->sizes();

            return [
                'available' => true,
                'ready' => $index->isReady(),
                'above' => $sizes['above'],
                'below' => $sizes['below'],
                'inflight' => $sizes['inflight'],
            ];
        } catch (Throwable) {
            return ['available' => false, 'ready' => false, 'above' => 0, 'below' => 0, 'inflight' => 0];
        }
    }

    /**
     * @return array{available: bool, pending: int, failed: int}
     */
    private function queueState(QueueFactory $queue): array
    {
        try {
            $pending = $queue->connection()->size(DeliverPriceAlert::QUEUE);
        } catch (Throwable) {
            return ['available' => false, 'pending' => 0, 'failed' => 0];
        }

        try {
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            $failed = 0;
        }

        return ['available' => true, 'pending' => $pending, 'failed' => $failed];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function alertsState(): array
    {
        return PriceAlert::query()
            ->with('user:id,email')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map($this->alert(...))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function alert(PriceAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'owner' => $alert->user->email,
            'direction' => $alert->direction->value,
            'target_price' => $alert->target_price->toDecimal(),
            'reference_price' => $alert->reference_price?->toDecimal(),
            'status' => $alert->status->value,
            'triggered_price' => $alert->triggered_price?->toDecimal(),
            'attempts' => $alert->attempts,
            'last_error' => $alert->last_error,
            'created_at' => $alert->created_at->toIso8601String(),
        ];
    }

    /**
     * The console acts as the seeded demo account, creating it when a fresh
     * database has not been seeded yet.
     */
    private function consoleUser(): User
    {
        return User::query()->firstOrCreate(
            ['email' => (string) config('gold.dev_console_email')],
            ['name' => 'Demo User', 'password' => Hash::make('password')],
        );
    }
}
