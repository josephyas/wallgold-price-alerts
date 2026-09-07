<?php

declare(strict_types=1);

namespace App\Alerts;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Exceptions\AlertRejected;
use App\Pricing\Price;
use Illuminate\Contracts\Config\Repository as Config;
use Throwable;

/**
 * Decides which side a new alert watches.
 *
 * Without an explicit direction the target is compared with the current
 * price: above it means "above", under it means "below", equal is refused
 * because it is ambiguous. An explicit direction is accepted as long as the
 * current price does not already satisfy it, and is the only way to create an
 * alert while the price feed is down.
 */
class DirectionResolver
{
    public function __construct(
        private readonly AlertIndex $index,
        private readonly Config $config,
    ) {}

    /**
     * @throws AlertRejected
     */
    public function resolve(Price $target, ?Direction $requested): ResolvedDirection
    {
        $current = $this->currentPrice();

        if ($requested === null) {
            if ($current === null) {
                throw AlertRejected::priceUnavailable();
            }

            if ($target->equals($current)) {
                throw AlertRejected::targetEqualsCurrentPrice($current);
            }

            return new ResolvedDirection(Direction::infer($target, $current), $current);
        }

        if ($current !== null && $requested->isHit($target, $current)) {
            throw AlertRejected::wouldTriggerImmediately($current);
        }

        return new ResolvedDirection($requested, $current);
    }

    /**
     * The last recorded price, unless the watcher has not recorded one recently
     * or the index cannot be reached: an outage counts as "price unknown".
     */
    public function currentPrice(): ?Price
    {
        try {
            $current = $this->index->currentPrice();
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($current === null || $current->isStale((int) $this->config->get('gold.price_max_age_ms'))) {
            return null;
        }

        return $current->quote->price;
    }
}
