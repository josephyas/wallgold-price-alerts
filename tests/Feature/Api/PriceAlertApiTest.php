<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Direction;
use App\Alerts\IndexRebuilder;
use App\Models\PriceAlert;
use App\Models\User;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PriceAlertApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AlertIndex $index;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        $this->index = $this->app->make(AlertIndex::class);
    }

    #[Test]
    public function creating_an_alert_infers_the_direction_records_the_reference_price_and_indexes_it(): void
    {
        $this->recordPrice('2650');
        $this->spy(IndexRebuilder::class);

        $response = $this->postJson('/api/alerts', ['target_price' => '2700']);

        $response->assertCreated()
            ->assertJsonPath('data.target_price', '2700.0000')
            ->assertJsonPath('data.direction', 'above')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.reference_price', '2650.0000')
            ->assertJsonPath('data.attempts', 0)
            ->assertJsonMissingPath('data.last_error');

        $id = $response->json('data.id');

        $this->assertDatabaseHas('price_alerts', ['id' => $id, 'user_id' => $this->user->id, 'target_price' => 27_000_000]);
        self::assertSame(['above' => 1, 'below' => 0, 'inflight' => 0], $this->index->sizes());
        self::assertSame([$id], $this->index->pop($this->quote('2700'), 10));
        $this->spy(IndexRebuilder::class)->shouldNotHaveReceived('rebuild');
    }

    #[Test]
    public function a_target_under_the_current_price_becomes_a_below_alert(): void
    {
        $this->recordPrice('2650');

        $this->postJson('/api/alerts', ['target_price' => 2600.5])
            ->assertCreated()
            ->assertJsonPath('data.direction', 'below')
            ->assertJsonPath('data.target_price', '2600.5000');

        self::assertSame(['above' => 0, 'below' => 1, 'inflight' => 0], $this->index->sizes());
    }

    #[Test]
    public function an_explicit_direction_allows_creating_alerts_while_the_price_is_unknown(): void
    {
        $this->postJson('/api/alerts', ['target_price' => '2700'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['direction']);

        $this->postJson('/api/alerts', ['target_price' => '2700', 'direction' => 'below'])
            ->assertCreated()
            ->assertJsonPath('data.direction', 'below')
            ->assertJsonPath('data.reference_price', null);
    }

    #[Test]
    public function a_stale_price_counts_as_unknown(): void
    {
        $this->recordPrice('2650');
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addMilliseconds(10_001));

        $this->postJson('/api/alerts', ['target_price' => '2700'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['direction']);
    }

    #[Test]
    public function a_target_equal_to_the_current_price_is_refused(): void
    {
        $this->recordPrice('2650');

        $this->postJson('/api/alerts', ['target_price' => '2650'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_price' => 'The target must differ from the current price (2650.0000).']);
    }

    #[Test]
    public function an_explicit_direction_the_price_already_satisfies_is_refused(): void
    {
        $this->recordPrice('2650');

        $this->postJson('/api/alerts', ['target_price' => '2600', 'direction' => 'above'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_price' => 'This alert would trigger immediately at the current price (2650.0000).']);
    }

    #[Test]
    public function the_target_price_is_validated(): void
    {
        $this->recordPrice('2650');

        foreach (['-1', '0', 'abc', '2700.12345', '', null] as $invalid) {
            $this->postJson('/api/alerts', ['target_price' => $invalid])->assertUnprocessable()->assertJsonValidationErrors(['target_price']);
        }

        $this->postJson('/api/alerts', ['target_price' => '2700', 'direction' => 'sideways'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['direction']);
    }

    #[Test]
    public function a_live_duplicate_is_refused_but_a_failed_one_is_replaced(): void
    {
        $this->recordPrice('2650');
        $this->postJson('/api/alerts', ['target_price' => '2700'])->assertCreated();

        $this->postJson('/api/alerts', ['target_price' => '2700'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_price' => 'You already have an alert for this price.']);

        $failed = PriceAlert::factory()->for($this->user)->failed()->above('2800')->create();
        $this->index->add($failed->id, Direction::Above, Price::fromDecimal('2800'));

        $response = $this->postJson('/api/alerts', ['target_price' => '2800'])->assertCreated();

        $this->assertDatabaseMissing('price_alerts', ['id' => $failed->id]);
        $this->assertDatabaseHas('price_alerts', ['id' => $response->json('data.id'), 'status' => 'active']);
        self::assertSame(['above' => 2, 'below' => 0, 'inflight' => 0], $this->index->sizes());
    }

    #[Test]
    public function a_user_cannot_hold_more_alerts_than_the_limit(): void
    {
        config()->set('gold.max_alerts_per_user', 2);
        $this->recordPrice('2650');
        PriceAlert::factory()->for($this->user)->count(2)->create();

        $this->postJson('/api/alerts', ['target_price' => '2700'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target_price' => 'You cannot hold more than 2 alerts.']);
    }

    #[Test]
    public function listing_shows_only_the_users_alerts_newest_first_with_an_optional_status_filter(): void
    {
        $older = PriceAlert::factory()->for($this->user)->above('2700')->create(['created_at' => now()->subMinute()]);
        $newer = PriceAlert::factory()->for($this->user)->failed()->above('2800')->create();
        PriceAlert::factory()->create();

        $this->getJson('/api/alerts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.0.last_error', 'Mailer refused the message.')
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/alerts?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $older->id);

        $this->getJson('/api/alerts?status=bogus')->assertUnprocessable();
        $this->getJson('/api/alerts?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 2);
    }

    #[Test]
    public function an_alert_can_be_shown_only_by_its_owner(): void
    {
        $mine = PriceAlert::factory()->for($this->user)->above('2700')->create();
        $theirs = PriceAlert::factory()->above('2700')->create();

        $this->getJson("/api/alerts/{$mine->id}")->assertOk()->assertJsonPath('data.id', $mine->id);
        $this->getJson("/api/alerts/{$theirs->id}")->assertForbidden();
        $this->getJson('/api/alerts/999999')->assertNotFound();
    }

    #[Test]
    public function cancelling_deletes_the_alert_and_removes_it_from_the_index(): void
    {
        $this->recordPrice('2650');
        $id = $this->postJson('/api/alerts', ['target_price' => '2700'])->json('data.id');

        $this->deleteJson("/api/alerts/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('price_alerts', ['id' => $id]);
        self::assertSame(['above' => 0, 'below' => 0, 'inflight' => 0], $this->index->sizes());
        $this->getJson("/api/alerts/{$id}")->assertNotFound();
    }

    #[Test]
    public function an_alert_being_delivered_cannot_be_cancelled(): void
    {
        $alert = PriceAlert::factory()->for($this->user)->sending()->create();

        $this->deleteJson("/api/alerts/{$alert->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'This alert is being delivered and can no longer be cancelled.');

        $this->assertDatabaseHas('price_alerts', ['id' => $alert->id]);
    }

    #[Test]
    public function only_the_owner_can_cancel(): void
    {
        $theirs = PriceAlert::factory()->create();

        $this->deleteJson("/api/alerts/{$theirs->id}")->assertForbidden();

        $this->assertDatabaseHas('price_alerts', ['id' => $theirs->id]);
    }

    private function recordPrice(string $price): void
    {
        $this->index->putCurrentPrice($this->quote($price));
    }

    private function quote(string $price): PriceQuote
    {
        return new PriceQuote(Price::fromDecimal($price), CarbonImmutable::now(), 'test');
    }
}
