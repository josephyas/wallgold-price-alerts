<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Alerts\Contracts\AlertIndex;
use App\Models\User;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RedisException;
use Tests\TestCase;

final class CurrentPriceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_unavailable_before_the_first_tick(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/price')
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'No gold price has been observed yet.');
    }

    #[Test]
    public function it_reports_the_last_recorded_price_and_whether_it_is_stale(): void
    {
        Sanctum::actingAs(User::factory()->create());
        CarbonImmutable::setTestNow('2026-09-07 12:00:00');
        $this->app->make(AlertIndex::class)->putCurrentPrice(
            new PriceQuote(Price::fromDecimal('2701.25'), CarbonImmutable::parse('2026-09-07 11:59:59'), 'fake'),
        );

        $this->getJson('/api/price')
            ->assertOk()
            ->assertJson(['data' => [
                'price' => '2701.2500',
                'unit' => 'USD/oz',
                'source' => 'fake',
                'observed_at' => '2026-09-07T11:59:59+00:00',
                'received_at' => '2026-09-07T12:00:00+00:00',
                'stale' => false,
            ]]);

        CarbonImmutable::setTestNow('2026-09-07 12:00:11');

        $this->getJson('/api/price')->assertOk()->assertJsonPath('data.stale', true);
    }

    #[Test]
    public function it_is_unavailable_while_the_index_cannot_be_reached(): void
    {
        Exceptions::fake();
        Sanctum::actingAs(User::factory()->create());
        $this->mock(AlertIndex::class, function (MockInterface $mock): void {
            $mock->shouldReceive('currentPrice')->once()->andThrow(new RedisException('Connection refused'));
        });

        $this->getJson('/api/price')
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'The gold price is currently unavailable.');

        Exceptions::assertReported(RedisException::class);
    }

    #[Test]
    public function price_and_alert_endpoints_require_a_token(): void
    {
        $this->getJson('/api/price')->assertUnauthorized();
        $this->getJson('/api/alerts')->assertUnauthorized();
        $this->postJson('/api/alerts', ['target_price' => '2700'])->assertUnauthorized();
        $this->deleteJson('/api/alerts/1')->assertUnauthorized();
    }
}
