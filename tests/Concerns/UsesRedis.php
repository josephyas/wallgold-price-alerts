<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use RuntimeException;
use Throwable;

/**
 * For tests that talk to a real Redis. They run against the dedicated test
 * database configured in phpunit.xml and are skipped when Redis is not
 * reachable, unless REDIS_REQUIRED=1 (as in CI) turns that into a failure.
 */
trait UsesRedis
{
    protected function setUpUsesRedis(): void
    {
        try {
            $this->redis()->ping();
        } catch (Throwable $e) {
            if (getenv('REDIS_REQUIRED') === '1') {
                throw new RuntimeException('Redis is required for this test run but is not reachable.', 0, $e);
            }

            $this->markTestSkipped('Redis is not reachable: '.$e->getMessage());
        }

        $this->redis()->flushdb();
    }

    protected function redis(): Connection
    {
        return $this->app->make(RedisFactory::class)->connection((string) config('gold.redis_connection'));
    }
}
