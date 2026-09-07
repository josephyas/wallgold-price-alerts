<?php

declare(strict_types=1);

namespace App\Pricing\Providers;

use App\Pricing\Contracts\ScriptedPrices;
use App\Pricing\Price;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

/**
 * Scripted prices shared between processes through a Redis list, so the
 * fake-push command can steer the watcher's fake feed.
 */
final class RedisScriptedPrices implements ScriptedPrices
{
    public const string KEY = 'price:fake:queue';

    public function __construct(
        private readonly RedisFactory $redis,
        private readonly string $connection = 'default',
    ) {}

    public function pull(): ?Price
    {
        $value = $this->redis->connection($this->connection)->lpop(self::KEY);

        return is_string($value) && $value !== '' ? Price::fromDecimal($value) : null;
    }

    public function push(Price ...$prices): void
    {
        if ($prices === []) {
            return;
        }

        $this->redis->connection($this->connection)->rpush(
            self::KEY,
            ...array_map(fn (Price $price): string => $price->toDecimal(), $prices),
        );
    }
}
