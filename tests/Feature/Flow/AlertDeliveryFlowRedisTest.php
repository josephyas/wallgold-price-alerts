<?php

declare(strict_types=1);

namespace Tests\Feature\Flow;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Index\RedisAlertIndex;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\UsesRedis;

#[Group('redis')]
final class AlertDeliveryFlowRedisTest extends AlertDeliveryFlowTestCase
{
    use UsesRedis;

    protected function index(): AlertIndex
    {
        return new RedisAlertIndex($this->app->make(RedisFactory::class), (string) config('gold.redis_connection'));
    }
}
