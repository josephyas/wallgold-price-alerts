<?php

declare(strict_types=1);

namespace App\Alerts;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Index\IndexEntry;
use App\Models\PriceAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Rebuilds the index from the active alert rows, which are the record of truth.
 */
class IndexRebuilder
{
    private const string LOCK = 'price-alerts:index:rebuild';

    public function __construct(private readonly AlertIndex $index) {}

    /** Returns the number of indexed alerts. Concurrent callers wait for one rebuild at a time. */
    public function rebuild(): int
    {
        return (int) Cache::lock(self::LOCK, 60)->block(30, function (): int {
            $entries = PriceAlert::query()
                ->active()
                ->select(['id', 'direction', 'target_price'])
                ->lazyById(1000)
                ->map(fn (PriceAlert $alert): IndexEntry => new IndexEntry($alert->id, $alert->direction, $alert->target_price));

            $count = $this->index->rebuild($entries);

            Log::info('price-alert.index.rebuilt', ['entries' => $count]);

            return $count;
        });
    }
}
