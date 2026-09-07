<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Index\InMemoryAlertIndex;
use App\Alerts\IndexRebuilder;
use App\Models\PriceAlert;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IndexRebuilderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_indexes_exactly_the_active_alerts(): void
    {
        $index = $this->app->make(AlertIndex::class);
        self::assertInstanceOf(InMemoryAlertIndex::class, $index);
        $index->markNotReady();
        $index->add(999, \App\Alerts\Direction::Above, Price::fromDecimal('1'));

        $above = PriceAlert::factory()->above('2700')->create();
        $below = PriceAlert::factory()->below('2600')->create();
        PriceAlert::factory()->sending()->above('2650')->create();
        PriceAlert::factory()->failed()->above('2660')->create();

        $count = $this->app->make(IndexRebuilder::class)->rebuild();

        self::assertSame(2, $count);
        self::assertTrue($index->isReady());
        self::assertSame(['above' => 1, 'below' => 1, 'inflight' => 0], $index->sizes(), 'the stale member 999 is gone');
        self::assertSame([$above->id], $index->pop(new PriceQuote(Price::fromDecimal('2700'), CarbonImmutable::now(), 'test'), 10));
        self::assertSame([$below->id], $index->pop(new PriceQuote(Price::fromDecimal('2600'), CarbonImmutable::now(), 'test'), 10));
    }
}
