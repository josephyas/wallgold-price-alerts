<?php

declare(strict_types=1);

namespace App\Alerts\Index;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Direction;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;

/**
 * Array-backed index with the same semantics as the Redis one, for tests and
 * for running the suite without Redis.
 */
final class InMemoryAlertIndex implements AlertIndex
{
    /** @var array<int, int> id => target minor */
    private array $above = [];

    /** @var array<int, int> */
    private array $below = [];

    /** @var array<string, int> member => popped at ms */
    private array $inflight = [];

    private bool $ready = false;

    private ?CurrentPrice $current = null;

    public function markReady(): self
    {
        $this->ready = true;

        return $this;
    }

    public function markNotReady(): self
    {
        $this->ready = false;

        return $this;
    }

    public function add(int $id, Direction $direction, Price $target): void
    {
        $this->side($direction)[$id] = $target->minor;
    }

    public function addMany(iterable $entries): void
    {
        foreach ($entries as $entry) {
            $this->add($entry->id, $entry->direction, $entry->target);
        }
    }

    public function remove(int $id): void
    {
        unset($this->above[$id], $this->below[$id]);
    }

    public function present(array $ids): array
    {
        $inflight = [];

        foreach (array_keys($this->inflight) as $member) {
            $inflight[(int) strtok($member, ':')] = true;
        }

        return array_values(array_filter(
            $ids,
            fn (int $id): bool => isset($this->above[$id]) || isset($this->below[$id]) || isset($inflight[$id]),
        ));
    }

    public function pop(PriceQuote $quote, int $limit): ?array
    {
        if (! $this->ready) {
            return null;
        }

        $this->putCurrentPrice($quote);

        $price = $quote->price->minor;
        $nowMs = (int) CarbonImmutable::now()->getPreciseTimestamp(3);

        $hits = $this->take($this->above, fn (int $target): bool => $target <= $price, $limit);
        $hits = [...$hits, ...$this->take($this->below, fn (int $target): bool => $target >= $price, $limit - count($hits))];

        foreach ($hits as $id) {
            $this->inflight[InflightEntry::memberFor($id, $quote->price)] = $nowMs;
        }

        return $hits;
    }

    public function ack(int $id, Price $price): void
    {
        unset($this->inflight[InflightEntry::memberFor($id, $price)]);
    }

    public function staleInflight(int $olderThanTimestampMs, int $limit): array
    {
        $entries = [];
        $sorted = $this->inflight;
        asort($sorted);

        foreach ($sorted as $member => $poppedAtMs) {
            if ($poppedAtMs > $olderThanTimestampMs || count($entries) >= $limit) {
                break;
            }

            $entries[] = InflightEntry::fromMember($member, $poppedAtMs);
        }

        return $entries;
    }

    public function touchInflight(array $entries, int $nowMs): void
    {
        foreach ($entries as $entry) {
            if (isset($this->inflight[$entry->member()])) {
                $this->inflight[$entry->member()] = $nowMs;
            }
        }
    }

    public function rebuild(iterable $entries): int
    {
        $this->above = [];
        $this->below = [];
        $this->addMany($entries);
        $this->ready = true;

        return count($this->above) + count($this->below);
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function sizes(): array
    {
        return [
            'above' => count($this->above),
            'below' => count($this->below),
            'inflight' => count($this->inflight),
        ];
    }

    public function currentPrice(): ?CurrentPrice
    {
        return $this->current;
    }

    public function putCurrentPrice(PriceQuote $quote): void
    {
        $this->current = new CurrentPrice($quote, CarbonImmutable::now());
    }

    /**
     * @return array<int, int>
     */
    private function &side(Direction $direction): array
    {
        if ($direction === Direction::Above) {
            return $this->above;
        }

        return $this->below;
    }

    /**
     * Remove and return up to $limit ids whose target satisfies the predicate, lowest target first.
     *
     * @param  array<int, int>  $side
     * @param  callable(int): bool  $matches
     * @return list<int>
     */
    private function take(array &$side, callable $matches, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        asort($side);
        $hits = [];

        foreach ($side as $id => $target) {
            if (count($hits) >= $limit) {
                break;
            }

            if ($matches($target)) {
                $hits[] = $id;
            }
        }

        foreach ($hits as $id) {
            unset($side[$id]);
        }

        return $hits;
    }
}
