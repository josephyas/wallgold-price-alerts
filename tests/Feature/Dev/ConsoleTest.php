<?php

declare(strict_types=1);

namespace Tests\Feature\Dev;

use App\Alerts\Contracts\AlertIndex;
use App\Models\PriceAlert;
use App\Models\User;
use App\Pricing\Contracts\ScriptedPrices;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/api/v1/messages*' => Http::response([
                'total' => 1,
                'messages' => [[
                    'ID' => 'abc',
                    'Subject' => 'Gold price alert: 2,700.00 USD/oz reached',
                    'To' => [['Address' => 'demo@example.com']],
                    'Created' => '2026-09-07T12:00:00Z',
                ]],
            ]),
        ]);
    }

    #[Test]
    public function the_console_page_renders(): void
    {
        $this->get('/dev')
            ->assertOk()
            ->assertSee('Alert Engine Console')
            ->assertSee('End-to-end test');
    }

    #[Test]
    public function the_console_is_gone_when_it_is_switched_off(): void
    {
        config()->set('gold.dev_console', false);

        $this->get('/dev')->assertNotFound();
        $this->getJson('/dev/state')->assertNotFound();
        $this->postJson('/dev/prices', ['prices' => ['2700']])->assertNotFound();
    }

    #[Test]
    public function the_state_endpoint_describes_the_whole_pipeline(): void
    {
        CarbonImmutable::setTestNow('2026-09-07 12:00:00');
        $index = $this->app->make(AlertIndex::class);
        $index->putCurrentPrice(new PriceQuote(Price::fromDecimal('2650.25'), CarbonImmutable::now(), 'fake'));
        $alert = PriceAlert::factory()->above('2700')->create();
        $index->add($alert->id, $alert->direction, $alert->target_price);

        $this->getJson('/dev/state')
            ->assertOk()
            ->assertJsonPath('price.price', '2650.2500')
            ->assertJsonPath('price.unit', 'USD/oz')
            ->assertJsonPath('price.stale', false)
            ->assertJsonPath('index.ready', true)
            ->assertJsonPath('index.above', 1)
            ->assertJsonPath('index.inflight', 0)
            ->assertJsonPath('queue.pending', 0)
            ->assertJsonPath('alerts.0.id', $alert->id)
            ->assertJsonPath('alerts.0.status', 'active')
            ->assertJsonPath('alerts.0.target_price', '2700.0000')
            ->assertJsonPath('inbox.available', true)
            ->assertJsonPath('inbox.total', 1);
    }

    #[Test]
    public function it_reports_an_unreachable_inbox_without_failing(): void
    {
        Http::fake(['*/api/v1/messages*' => Http::failedConnection()]);

        $this->getJson('/dev/state')
            ->assertOk()
            ->assertJsonPath('inbox.available', false)
            ->assertJsonPath('inbox.total', 0);
    }

    #[Test]
    public function prices_can_be_pushed_to_the_fake_feed(): void
    {
        $this->postJson('/dev/prices', ['prices' => ['2690', '2701.25']])
            ->assertAccepted()
            ->assertJsonPath('pushed', ['2690.0000', '2701.2500']);

        $script = $this->app->make(ScriptedPrices::class);

        self::assertSame('2690.0000', $script->pull()?->toDecimal());
        self::assertSame('2701.2500', $script->pull()?->toDecimal());
        self::assertNull($script->pull());
    }

    #[Test]
    public function pushing_an_invalid_price_is_refused(): void
    {
        $this->postJson('/dev/prices', ['prices' => ['2690', 'abc']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prices.1']);

        self::assertNull($this->app->make(ScriptedPrices::class)->pull());
    }

    #[Test]
    public function an_alert_can_be_created_and_is_indexed(): void
    {
        $index = $this->app->make(AlertIndex::class);
        $index->putCurrentPrice(new PriceQuote(Price::fromDecimal('2650'), CarbonImmutable::now(), 'fake'));

        $id = $this->postJson('/dev/alerts', ['target_price' => '2700'])
            ->assertCreated()
            ->assertJsonPath('alert.direction', 'above')
            ->assertJsonPath('alert.owner', 'demo@example.com')
            ->json('alert.id');

        $this->assertDatabaseHas('price_alerts', ['id' => $id, 'status' => 'active']);
        self::assertSame(1, $index->sizes()['above']);
        self::assertSame('demo@example.com', User::query()->findOrFail(PriceAlert::query()->findOrFail($id)->user_id)->email);
    }

    #[Test]
    public function an_alert_can_be_cancelled_from_the_console(): void
    {
        $alert = PriceAlert::factory()->above('2700')->create();
        $index = $this->app->make(AlertIndex::class);
        $index->add($alert->id, $alert->direction, $alert->target_price);

        $this->deleteJson('/dev/alerts/'.$alert->id)->assertNoContent();

        $this->assertDatabaseMissing('price_alerts', ['id' => $alert->id]);
        self::assertSame(0, $index->sizes()['above']);
    }

    #[Test]
    public function an_alert_being_delivered_cannot_be_cancelled(): void
    {
        $alert = PriceAlert::factory()->sending()->above('2700')->create();

        $this->deleteJson('/dev/alerts/'.$alert->id)->assertConflict();

        $this->assertDatabaseHas('price_alerts', ['id' => $alert->id]);
    }

    #[Test]
    public function the_inbox_can_be_cleared(): void
    {
        Http::fake(['*/api/v1/messages*' => Http::response([], 200)]);

        $this->postJson('/dev/inbox/clear')->assertOk()->assertJsonPath('cleared', true);
    }
}
