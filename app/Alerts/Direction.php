<?php

declare(strict_types=1);

namespace App\Alerts;

use App\Pricing\Price;
use InvalidArgumentException;

/**
 * Which way the price has to move for an alert to fire.
 *
 * "Above" alerts fire once the price is at or over the target, "Below" alerts
 * once it is at or under it. The test is inclusive and applied to the latest
 * observation, so a price that jumps over a target still triggers it.
 */
enum Direction: string
{
    case Above = 'above';
    case Below = 'below';

    public function isHit(Price $target, Price $price): bool
    {
        return match ($this) {
            self::Above => $price->isAtLeast($target),
            self::Below => $price->isAtMost($target),
        };
    }

    /**
     * Work out the direction from where the target sits relative to the current price.
     *
     * @throws InvalidArgumentException when the target equals the current price
     */
    public static function infer(Price $target, Price $current): self
    {
        return match ($target->compare($current)) {
            1 => self::Above,
            -1 => self::Below,
            default => throw new InvalidArgumentException('The target price must differ from the current price.'),
        };
    }

    /** Verb used in notifications: the price "rose to" or "fell to" the target. */
    public function movement(): string
    {
        return match ($this) {
            self::Above => 'rose to',
            self::Below => 'fell to',
        };
    }
}
