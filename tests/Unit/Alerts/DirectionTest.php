<?php

declare(strict_types=1);

namespace Tests\Unit\Alerts;

use App\Alerts\Direction;
use App\Pricing\Price;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DirectionTest extends TestCase
{
    #[Test]
    public function above_fires_at_or_over_the_target(): void
    {
        $target = Price::fromDecimal('2700');

        self::assertFalse(Direction::Above->isHit($target, Price::fromDecimal('2699.9999')));
        self::assertTrue(Direction::Above->isHit($target, Price::fromDecimal('2700')));
        self::assertTrue(Direction::Above->isHit($target, Price::fromDecimal('2750')));
    }

    #[Test]
    public function below_fires_at_or_under_the_target(): void
    {
        $target = Price::fromDecimal('2600');

        self::assertFalse(Direction::Below->isHit($target, Price::fromDecimal('2600.0001')));
        self::assertTrue(Direction::Below->isHit($target, Price::fromDecimal('2600')));
        self::assertTrue(Direction::Below->isHit($target, Price::fromDecimal('2550')));
    }

    #[Test]
    public function it_infers_the_direction_from_the_current_price(): void
    {
        $current = Price::fromDecimal('2650');

        self::assertSame(Direction::Above, Direction::infer(Price::fromDecimal('2700'), $current));
        self::assertSame(Direction::Below, Direction::infer(Price::fromDecimal('2600'), $current));
    }

    #[Test]
    public function it_cannot_infer_a_direction_when_the_target_equals_the_current_price(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Direction::infer(Price::fromDecimal('2650'), Price::fromDecimal('2650'));
    }

    #[Test]
    public function it_describes_the_price_movement(): void
    {
        self::assertSame('rose to', Direction::Above->movement());
        self::assertSame('fell to', Direction::Below->movement());
    }
}
