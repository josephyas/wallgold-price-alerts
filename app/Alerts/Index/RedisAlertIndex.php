<?php

declare(strict_types=1);

namespace App\Alerts\Index;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Direction;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\PhpRedisConnection;
use RuntimeException;

/**
 * Sorted-set index: score = target price in minor units, member = alert id.
 *
 * A tick is one EVAL: it checks the ready flag, records the current price,
 * takes every "above" alert whose target is at or under the price and every
 * "below" alert whose target is at or over it, removes them from their sets
 * and parks them in the in-flight set. Because the script runs atomically,
 * concurrent tickers can never pop the same alert twice.
 *
 * Every key goes through KEYS[] so the client-side prefix applies to it. The
 * phpredis client is required: its Laravel wrapper takes Lua scripts in the
 * native (script, number of keys, ...keys, ...args) order used here.
 */
final class RedisAlertIndex implements AlertIndex
{
    public const string KEY_ABOVE = 'alerts:above';

    public const string KEY_BELOW = 'alerts:below';

    public const string KEY_INFLIGHT = 'alerts:inflight';

    public const string KEY_READY = 'alerts:ready';

    public const string KEY_CURRENT = 'price:current';

    private const string BUILDING_SUFFIX = ':building';

    /** Redis 6.2+ (ZRANGE ... BYSCORE). Limit must stay small enough for unpack(). */
    public const string POP_SCRIPT = <<<'LUA'
        -- KEYS: 1 above, 2 below, 3 inflight, 4 ready, 5 current
        -- ARGV: 1 price_minor, 2 now_ms, 3 limit, 4 source, 5 observed_at_ms
        if redis.call('EXISTS', KEYS[4]) == 0 then
            return false
        end
        redis.call('HSET', KEYS[5], 'price', ARGV[1], 'observed_at', ARGV[5], 'received_at', ARGV[2], 'source', ARGV[4])
        local limit = tonumber(ARGV[3])
        local above = redis.call('ZRANGE', KEYS[1], '-inf', ARGV[1], 'BYSCORE', 'LIMIT', 0, limit)
        local below = {}
        if #above < limit then
            below = redis.call('ZRANGE', KEYS[2], ARGV[1], '+inf', 'BYSCORE', 'LIMIT', 0, limit - #above)
        end
        if #above > 0 then redis.call('ZREM', KEYS[1], unpack(above)) end
        if #below > 0 then redis.call('ZREM', KEYS[2], unpack(below)) end
        local hits, inflight = {}, {}
        for _, id in ipairs(above) do
            hits[#hits + 1] = id
            inflight[#inflight + 1] = ARGV[2]
            inflight[#inflight + 1] = id .. ':' .. ARGV[1]
        end
        for _, id in ipairs(below) do
            hits[#hits + 1] = id
            inflight[#inflight + 1] = ARGV[2]
            inflight[#inflight + 1] = id .. ':' .. ARGV[1]
        end
        if #inflight > 0 then redis.call('ZADD', KEYS[3], unpack(inflight)) end
        return hits
        LUA;

    /** Swap freshly built level sets into place and raise the ready flag, in one step. */
    public const string SWAP_SCRIPT = <<<'LUA'
        -- KEYS: 1 above, 2 below, 3 above:building, 4 below:building, 5 ready
        for i = 1, 2 do
            if redis.call('EXISTS', KEYS[i + 2]) == 1 then
                redis.call('RENAME', KEYS[i + 2], KEYS[i])
            else
                redis.call('DEL', KEYS[i])
            end
        end
        redis.call('SET', KEYS[5], '1')
        return 1
        LUA;

    private const int REBUILD_CHUNK = 1000;

    public function __construct(
        private readonly RedisFactory $redis,
        private readonly string $connection = 'default',
    ) {}

    public function add(int $id, Direction $direction, Price $target): void
    {
        $this->redis()->zadd(self::keyFor($direction), [$id => $target->minor]);
    }

    public function addMany(iterable $entries): void
    {
        foreach ($this->chunkByDirection($entries) as $direction => $members) {
            $this->redis()->zadd(self::keyFor(Direction::from($direction)), $members);
        }
    }

    public function remove(int $id): void
    {
        $redis = $this->redis();

        $redis->zrem(self::KEY_ABOVE, (string) $id);
        $redis->zrem(self::KEY_BELOW, (string) $id);
    }

    public function pop(PriceQuote $quote, int $limit): ?array
    {
        $result = $this->redis()->eval(
            self::POP_SCRIPT,
            5,
            self::KEY_ABOVE,
            self::KEY_BELOW,
            self::KEY_INFLIGHT,
            self::KEY_READY,
            self::KEY_CURRENT,
            (string) $quote->price->minor,
            (string) self::nowMs(),
            (string) $limit,
            $quote->source,
            (string) $quote->observedAtMs(),
        );

        if (! is_array($result)) {
            return null;
        }

        return array_map(intval(...), array_values($result));
    }

    public function ack(int $id, Price $price): void
    {
        $this->redis()->zrem(self::KEY_INFLIGHT, InflightEntry::memberFor($id, $price));
    }

    public function staleInflight(int $olderThanTimestampMs, int $limit): array
    {
        /** @var array<string, string|float> $members */
        $members = $this->redis()->zrangebyscore(self::KEY_INFLIGHT, '-inf', (string) $olderThanTimestampMs, [
            'withscores' => true,
            'limit' => ['offset' => 0, 'count' => $limit],
        ]);

        $entries = [];

        foreach ($members as $member => $score) {
            $entries[] = InflightEntry::fromMember((string) $member, (int) $score);
        }

        return $entries;
    }

    public function touchInflight(array $entries, int $nowMs): void
    {
        if ($entries === []) {
            return;
        }

        $members = [];

        foreach ($entries as $entry) {
            $members[$entry->member()] = $nowMs;
        }

        $this->redis()->zadd(self::KEY_INFLIGHT, 'xx', $members);
    }

    public function rebuild(iterable $entries): int
    {
        $redis = $this->redis();
        $aboveBuilding = self::KEY_ABOVE.self::BUILDING_SUFFIX;
        $belowBuilding = self::KEY_BELOW.self::BUILDING_SUFFIX;

        $redis->del($aboveBuilding, $belowBuilding);

        $count = 0;

        foreach ($this->chunkByDirection($entries, self::REBUILD_CHUNK) as $direction => $members) {
            $key = Direction::from($direction) === Direction::Above ? $aboveBuilding : $belowBuilding;
            $redis->zadd($key, $members);
            $count += count($members);
        }

        $redis->eval(self::SWAP_SCRIPT, 5, self::KEY_ABOVE, self::KEY_BELOW, $aboveBuilding, $belowBuilding, self::KEY_READY);

        return $count;
    }

    public function isReady(): bool
    {
        return (int) $this->redis()->exists(self::KEY_READY) === 1;
    }

    public function sizes(): array
    {
        $redis = $this->redis();

        return [
            'above' => (int) $redis->zcard(self::KEY_ABOVE),
            'below' => (int) $redis->zcard(self::KEY_BELOW),
            'inflight' => (int) $redis->zcard(self::KEY_INFLIGHT),
        ];
    }

    public function currentPrice(): ?CurrentPrice
    {
        /** @var array<string, string> $hash */
        $hash = $this->redis()->hgetall(self::KEY_CURRENT);

        if (! isset($hash['price'], $hash['observed_at'], $hash['received_at'], $hash['source'])) {
            return null;
        }

        return new CurrentPrice(
            new PriceQuote(
                Price::fromMinor((int) $hash['price']),
                CarbonImmutable::createFromTimestampMs((int) $hash['observed_at'], 'UTC'),
                $hash['source'],
            ),
            CarbonImmutable::createFromTimestampMs((int) $hash['received_at'], 'UTC'),
        );
    }

    public function putCurrentPrice(PriceQuote $quote): void
    {
        $this->redis()->hmset(self::KEY_CURRENT, [
            'price' => (string) $quote->price->minor,
            'observed_at' => (string) $quote->observedAtMs(),
            'received_at' => (string) self::nowMs(),
            'source' => $quote->source,
        ]);
    }

    public static function keyFor(Direction $direction): string
    {
        return $direction === Direction::Above ? self::KEY_ABOVE : self::KEY_BELOW;
    }

    private function redis(): PhpRedisConnection
    {
        $connection = $this->redis->connection($this->connection);

        if (! $connection instanceof PhpRedisConnection) {
            throw new RuntimeException('The alert index requires the phpredis client (REDIS_CLIENT=phpredis).');
        }

        return $connection;
    }

    /**
     * Group entries into ZADD dictionaries per direction, yielding at most $chunk members at a time.
     *
     * @param  iterable<IndexEntry>  $entries
     * @return iterable<string, array<int, int>>
     */
    private function chunkByDirection(iterable $entries, int $chunk = PHP_INT_MAX): iterable
    {
        $buffers = [Direction::Above->value => [], Direction::Below->value => []];

        foreach ($entries as $entry) {
            $buffers[$entry->direction->value][$entry->id] = $entry->target->minor;

            if (count($buffers[$entry->direction->value]) >= $chunk) {
                yield $entry->direction->value => $buffers[$entry->direction->value];
                $buffers[$entry->direction->value] = [];
            }
        }

        foreach ($buffers as $direction => $members) {
            if ($members !== []) {
                yield $direction => $members;
            }
        }
    }

    private static function nowMs(): int
    {
        return (int) CarbonImmutable::now()->getPreciseTimestamp(3);
    }
}
