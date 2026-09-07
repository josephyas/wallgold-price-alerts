<?php

declare(strict_types=1);

namespace App\Alerts\Contracts;

use App\Alerts\Direction;
use App\Alerts\Index\CurrentPrice;
use App\Alerts\Index\IndexEntry;
use App\Alerts\Index\InflightEntry;
use App\Pricing\Price;
use App\Pricing\PriceQuote;

/**
 * Fast lookup of the alerts a price tick triggers.
 *
 * The index is a rebuildable projection of the active alert rows, never the
 * record of truth: it may hold stale members, and it is rebuilt from the
 * database whenever it is found not ready or drifting.
 */
interface AlertIndex
{
    public function add(int $id, Direction $direction, Price $target): void;

    /**
     * @param  iterable<IndexEntry>  $entries
     */
    public function addMany(iterable $entries): void;

    public function remove(int $id): void;

    /**
     * Atomically take every alert the quote triggers, up to the limit, and
     * record the quote as the current price. Returns null while the index has
     * not been built, so the caller can rebuild it first.
     *
     * @return list<int>|null
     */
    public function pop(PriceQuote $quote, int $limit): ?array;

    /** Confirm that a popped alert has been dealt with. */
    public function ack(int $id, Price $price): void;

    /**
     * Popped alerts that were not acknowledged since the given moment.
     *
     * @return list<InflightEntry>
     */
    public function staleInflight(int $olderThanTimestampMs, int $limit): array;

    /**
     * Mark in-flight entries as seen now, so they are not reported again until they age out.
     *
     * @param  list<InflightEntry>  $entries
     */
    public function touchInflight(array $entries, int $nowMs): void;

    /**
     * Replace the level sets with the given entries and mark the index ready.
     * In-flight entries are kept. Returns the number of indexed entries.
     *
     * @param  iterable<IndexEntry>  $entries
     */
    public function rebuild(iterable $entries): int;

    public function isReady(): bool;

    /**
     * @return array{above: int, below: int, inflight: int}
     */
    public function sizes(): array;

    public function currentPrice(): ?CurrentPrice;

    public function putCurrentPrice(PriceQuote $quote): void;
}
