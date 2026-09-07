<?php

declare(strict_types=1);

namespace App\Alerts\Exceptions;

use App\Pricing\Price;
use Illuminate\Validation\ValidationException;

/**
 * A rule of the alert domain refused the request. Rendered as a 422 with the
 * offending field, exactly like input validation, because to the client that
 * is what it is.
 */
final class AlertRejected extends ValidationException
{
    public static function priceUnavailable(): self
    {
        return self::withMessages(['direction' => 'The current price is unavailable; specify the direction explicitly.']);
    }

    public static function targetEqualsCurrentPrice(Price $current): self
    {
        return self::withMessages(['target_price' => sprintf('The target must differ from the current price (%s).', $current->toDecimal())]);
    }

    public static function wouldTriggerImmediately(Price $current): self
    {
        return self::withMessages(['target_price' => sprintf('This alert would trigger immediately at the current price (%s).', $current->toDecimal())]);
    }

    public static function duplicate(): self
    {
        return self::withMessages(['target_price' => 'You already have an alert for this price.']);
    }

    public static function limitReached(int $limit): self
    {
        return self::withMessages(['target_price' => sprintf('You cannot hold more than %d alerts.', $limit)]);
    }
}
