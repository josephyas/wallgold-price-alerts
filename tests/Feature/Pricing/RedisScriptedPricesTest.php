<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Pricing\Price;
use App\Pricing\Providers\RedisScriptedPrices;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\UsesRedis;
use Tests\TestCase;

#[Group('redis')]
final class RedisScriptedPricesTest extends TestCase
{
    use UsesRedis;

    #[Test]
    public function prices_are_served_in_the_order_they_were_pushed(): void
    {
        $script = new RedisScriptedPrices($this->app->make(RedisFactory::class), (string) config('gold.redis_connection'));

        self::assertNull($script->pull());

        $script->push(Price::fromDecimal('2690'), Price::fromDecimal('2701.25'));
        $script->push();

        self::assertSame('2690.0000', $script->pull()?->toDecimal());
        self::assertSame('2701.2500', $script->pull()?->toDecimal());
        self::assertNull($script->pull());
    }
}
