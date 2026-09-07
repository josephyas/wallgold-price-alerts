<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Alerts\AlertStatus;
use App\Alerts\Direction;
use App\Models\PriceAlert;
use App\Models\User;
use App\Pricing\Price;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PriceAlertModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_prices_as_minor_units_and_reads_them_back_as_value_objects(): void
    {
        $alert = PriceAlert::factory()->above('2700.5')->create(['reference_price' => '2650.1234']);

        $this->assertDatabaseHas('price_alerts', [
            'id' => $alert->id,
            'target_price' => 27_005_000,
            'reference_price' => 26_501_234,
            'direction' => 'above',
            'status' => 'active',
        ]);

        $fresh = $alert->fresh();

        self::assertInstanceOf(Price::class, $fresh?->target_price);
        self::assertSame('2700.5000', $fresh->target_price->toDecimal());
        self::assertSame('2650.1234', $fresh->reference_price?->toDecimal());
        self::assertSame(Direction::Above, $fresh->direction);
        self::assertSame(AlertStatus::Active, $fresh->status);
        self::assertNull($fresh->triggered_price);
        self::assertSame(0, $fresh->attempts);
    }

    #[Test]
    public function it_knows_when_a_price_hits_it(): void
    {
        $above = PriceAlert::factory()->above('2700')->make();
        $below = PriceAlert::factory()->below('2600')->make();

        self::assertTrue($above->isHitBy(Price::fromDecimal('2700')));
        self::assertFalse($above->isHitBy(Price::fromDecimal('2699.9999')));
        self::assertTrue($below->isHitBy(Price::fromDecimal('2600')));
        self::assertFalse($below->isHitBy(Price::fromDecimal('2600.0001')));
    }

    #[Test]
    public function the_active_scope_excludes_sending_and_failed_alerts(): void
    {
        $active = PriceAlert::factory()->create();
        PriceAlert::factory()->sending()->create();
        PriceAlert::factory()->failed()->create();

        self::assertSame([$active->id], PriceAlert::query()->active()->pluck('id')->all());
        self::assertTrue($active->isActive());
    }

    #[Test]
    public function the_owned_by_scope_limits_alerts_to_one_user(): void
    {
        $owner = User::factory()->create();
        $mine = PriceAlert::factory()->for($owner)->create();
        PriceAlert::factory()->create();

        self::assertSame([$mine->id], PriceAlert::query()->ownedBy($owner)->pluck('id')->all());
        self::assertSame([$mine->id], $owner->priceAlerts()->pluck('id')->all());
        self::assertTrue($mine->user->is($owner));
    }

    #[Test]
    public function a_user_cannot_have_two_alerts_at_the_same_level_and_side(): void
    {
        $owner = User::factory()->create();
        PriceAlert::factory()->for($owner)->above('2700')->create();

        $this->expectException(UniqueConstraintViolationException::class);

        PriceAlert::factory()->for($owner)->above('2700')->create();
    }

    #[Test]
    public function the_same_level_is_allowed_on_the_other_side_or_for_another_user(): void
    {
        $owner = User::factory()->create();
        PriceAlert::factory()->for($owner)->above('2700')->create();
        PriceAlert::factory()->for($owner)->below('2700')->create();
        PriceAlert::factory()->above('2700')->create();

        self::assertSame(3, PriceAlert::query()->count());
    }

    #[Test]
    public function deleting_a_user_removes_their_alerts(): void
    {
        $owner = User::factory()->create();
        PriceAlert::factory()->for($owner)->count(2)->create();

        $owner->delete();

        self::assertSame(0, PriceAlert::query()->count());
    }
}
