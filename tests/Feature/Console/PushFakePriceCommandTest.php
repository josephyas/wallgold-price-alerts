<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Pricing\Contracts\ScriptedPrices;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PushFakePriceCommandTest extends TestCase
{
    #[Test]
    public function it_queues_prices_for_the_fake_feed_in_order(): void
    {
        $this->artisan('price:fake-push 2690 2701.25')
            ->expectsOutputToContain('Queued 2 price(s)')
            ->assertSuccessful();

        $script = $this->app->make(ScriptedPrices::class);

        self::assertSame('2690.0000', $script->pull()?->toDecimal());
        self::assertSame('2701.2500', $script->pull()?->toDecimal());
        self::assertNull($script->pull());
    }

    #[Test]
    public function it_rejects_an_invalid_price(): void
    {
        $this->artisan('price:fake-push 2690 abc')
            ->expectsOutputToContain('not a valid price')
            ->assertExitCode(2);

        self::assertNull($this->app->make(ScriptedPrices::class)->pull());
    }
}
