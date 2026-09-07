<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Direction;
use App\Alerts\Index\IndexEntry;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Behaviour every AlertIndex implementation must share.
 */
abstract class AlertIndexContractTestCase extends TestCase
{
    protected AlertIndex $index;

    abstract protected function makeIndex(): AlertIndex;

    protected function setUp(): void
    {
        parent::setUp();

        $this->index = $this->makeIndex();
    }

    #[Test]
    public function it_is_not_ready_until_it_has_been_built(): void
    {
        self::assertFalse($this->index->isReady());
        self::assertNull($this->index->pop($this->quote('2700'), 100));

        self::assertSame(0, $this->index->rebuild([]));

        self::assertTrue($this->index->isReady());
        self::assertSame([], $this->index->pop($this->quote('2700'), 100));
    }

    #[Test]
    public function it_pops_above_alerts_at_or_under_the_price_and_below_alerts_at_or_over_it(): void
    {
        $this->index->rebuild([]);
        $this->index->add(1, Direction::Above, Price::fromDecimal('2700'));
        $this->index->add(2, Direction::Above, Price::fromDecimal('2700.0001'));
        $this->index->add(3, Direction::Below, Price::fromDecimal('2700'));
        $this->index->add(4, Direction::Below, Price::fromDecimal('2699.9999'));
        $this->index->add(5, Direction::Above, Price::fromDecimal('2600'));
        $this->index->add(6, Direction::Below, Price::fromDecimal('2800'));

        $popped = $this->index->pop($this->quote('2700'), 100);

        self::assertNotNull($popped);
        sort($popped);
        self::assertSame([1, 3, 5, 6], $popped);
        self::assertSame(['above' => 1, 'below' => 1, 'inflight' => 4], $this->index->sizes());
    }

    #[Test]
    public function a_second_pop_at_the_same_price_returns_nothing(): void
    {
        $this->index->rebuild([new IndexEntry(1, Direction::Above, Price::fromDecimal('2700'))]);

        self::assertSame([1], $this->index->pop($this->quote('2750'), 100));
        self::assertSame([], $this->index->pop($this->quote('2750'), 100));
        self::assertSame([], $this->index->pop($this->quote('2800'), 100));
    }

    #[Test]
    public function it_pops_in_batches_of_the_requested_size(): void
    {
        $entries = [];

        foreach (range(1, 1500) as $id) {
            $entries[] = new IndexEntry($id, $id % 2 === 0 ? Direction::Above : Direction::Below, Price::fromDecimal('2700'));
        }

        self::assertSame(1500, $this->index->rebuild($entries));

        $first = $this->index->pop($this->quote('2700'), 1000);
        $second = $this->index->pop($this->quote('2700'), 1000);
        $third = $this->index->pop($this->quote('2700'), 1000);

        self::assertCount(1000, $first ?? []);
        self::assertCount(500, $second ?? []);
        self::assertSame([], $third);

        $all = [...$first ?? [], ...$second ?? []];
        sort($all);
        self::assertSame(range(1, 1500), $all);
        self::assertSame(['above' => 0, 'below' => 0, 'inflight' => 1500], $this->index->sizes());
    }

    #[Test]
    public function popping_records_the_current_price(): void
    {
        CarbonImmutable::setTestNow('2026-09-07 12:00:00.500');
        $this->index->rebuild([]);

        self::assertNull($this->index->currentPrice());

        $quote = new PriceQuote(Price::fromDecimal('2701.25'), CarbonImmutable::parse('2026-09-07 11:59:59.750'), 'fake');
        $this->index->pop($quote, 100);

        $current = $this->index->currentPrice();

        self::assertNotNull($current);
        self::assertSame('2701.2500', $current->quote->price->toDecimal());
        self::assertSame('fake', $current->quote->source);
        self::assertSame($quote->observedAtMs(), $current->quote->observedAtMs());
        self::assertSame((int) CarbonImmutable::now()->getPreciseTimestamp(3), $current->receivedAtMs());
        self::assertFalse($current->isStale(1_000, $current->receivedAtMs() + 1_000));
        self::assertTrue($current->isStale(1_000, $current->receivedAtMs() + 1_001));
    }

    #[Test]
    public function the_current_price_can_be_stored_directly(): void
    {
        $this->index->putCurrentPrice($this->quote('2650'));

        self::assertSame('2650.0000', $this->index->currentPrice()?->quote->price->toDecimal());
    }

    #[Test]
    public function acknowledging_removes_the_inflight_entry(): void
    {
        $this->index->rebuild([new IndexEntry(7, Direction::Below, Price::fromDecimal('2600'))]);
        $quote = $this->quote('2590');

        self::assertSame([7], $this->index->pop($quote, 100));
        self::assertSame(1, $this->index->sizes()['inflight']);

        $this->index->ack(7, Price::fromDecimal('2000'));
        self::assertSame(1, $this->index->sizes()['inflight'], 'an ack for a different price is a no-op');

        $this->index->ack(7, $quote->price);
        self::assertSame(0, $this->index->sizes()['inflight']);
    }

    #[Test]
    public function stale_inflight_entries_can_be_listed_and_touched(): void
    {
        CarbonImmutable::setTestNow('2026-09-07 12:00:00');
        $poppedAt = (int) CarbonImmutable::now()->getPreciseTimestamp(3);

        $this->index->rebuild([
            new IndexEntry(1, Direction::Above, Price::fromDecimal('2700')),
            new IndexEntry(2, Direction::Above, Price::fromDecimal('2700')),
        ]);
        $this->index->pop($this->quote('2750'), 100);

        self::assertSame([], $this->index->staleInflight($poppedAt - 1, 10));

        $stale = $this->index->staleInflight($poppedAt, 10);
        self::assertCount(2, $stale);
        self::assertSame($poppedAt, $stale[0]->poppedAtMs);
        self::assertSame('2750.0000', $stale[0]->price->toDecimal());
        self::assertSame([1, 2], array_map(fn ($entry): int => $entry->id, $stale));

        self::assertCount(1, $this->index->staleInflight($poppedAt, 1));

        $this->index->touchInflight($stale, $poppedAt + 5_000);

        self::assertSame([], $this->index->staleInflight($poppedAt, 10));
        self::assertCount(2, $this->index->staleInflight($poppedAt + 5_000, 10));
    }

    #[Test]
    public function rebuilding_replaces_the_levels_but_keeps_inflight_entries(): void
    {
        $this->index->rebuild([new IndexEntry(1, Direction::Above, Price::fromDecimal('2700'))]);
        $this->index->add(2, Direction::Below, Price::fromDecimal('2600'));
        $this->index->pop($this->quote('2750'), 100);

        self::assertSame(['above' => 0, 'below' => 1, 'inflight' => 1], $this->index->sizes());

        $count = $this->index->rebuild([
            new IndexEntry(3, Direction::Above, Price::fromDecimal('2800')),
            new IndexEntry(4, Direction::Below, Price::fromDecimal('2500')),
            new IndexEntry(5, Direction::Below, Price::fromDecimal('2550')),
        ]);

        self::assertSame(3, $count);
        self::assertTrue($this->index->isReady());
        self::assertSame(['above' => 1, 'below' => 2, 'inflight' => 1], $this->index->sizes());
        self::assertSame([], $this->index->pop($this->quote('2750'), 100), 'alert 2 is gone, alert 1 is in flight');
    }

    #[Test]
    public function removing_an_alert_takes_it_out_of_both_sides(): void
    {
        $this->index->rebuild([
            new IndexEntry(1, Direction::Above, Price::fromDecimal('2700')),
            new IndexEntry(2, Direction::Below, Price::fromDecimal('2600')),
        ]);

        $this->index->remove(1);
        $this->index->remove(2);
        $this->index->remove(99);

        self::assertSame(['above' => 0, 'below' => 0, 'inflight' => 0], $this->index->sizes());
    }

    #[Test]
    public function entries_can_be_added_in_bulk(): void
    {
        $this->index->rebuild([]);
        $this->index->addMany([
            new IndexEntry(1, Direction::Above, Price::fromDecimal('2700')),
            new IndexEntry(2, Direction::Below, Price::fromDecimal('2600')),
        ]);

        self::assertSame(['above' => 1, 'below' => 1, 'inflight' => 0], $this->index->sizes());
    }

    protected function quote(string $price): PriceQuote
    {
        return new PriceQuote(Price::fromDecimal($price), CarbonImmutable::now(), 'test');
    }
}
