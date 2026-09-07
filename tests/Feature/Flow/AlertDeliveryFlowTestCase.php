<?php

declare(strict_types=1);

namespace Tests\Feature\Flow;

use App\Alerts\Contracts\AlertIndex;
use App\Models\User;
use App\Notifications\PriceAlertTriggered;
use App\Pricing\Contracts\PriceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\SequencePriceProvider;
use Tests\TestCase;

/**
 * The whole story, from registration to the email, through the real HTTP
 * layer, the real watcher command and the sync queue. Concrete subclasses
 * choose the index implementation.
 */
abstract class AlertDeliveryFlowTestCase extends TestCase
{
    use RefreshDatabase;

    abstract protected function index(): AlertIndex;

    #[Test]
    public function a_user_is_emailed_once_when_the_price_crosses_their_target_and_the_alert_disappears(): void
    {
        Notification::fake();
        $index = $this->index();
        $this->app->instance(AlertIndex::class, $index);
        $this->app->instance(PriceProvider::class, new SequencePriceProvider(['2650', '2701.25', '2702', '2650']));

        // First tick: the index is built from the (empty) database and the price is recorded.
        $this->artisan('price:watch --once')->assertSuccessful();

        $token = $this->postJson('/api/auth/register', [
            'name' => 'Sara',
            'email' => 'sara@example.com',
            'password' => 'correct-horse-battery',
        ])->assertCreated()->json('token');

        $auth = ['Authorization' => "Bearer {$token}"];

        $this->withHeaders($auth)->getJson('/api/price')->assertOk()->assertJsonPath('data.price', '2650.0000');

        $alert = $this->withHeaders($auth)->postJson('/api/alerts', ['target_price' => '2700'])
            ->assertCreated()
            ->assertJsonPath('data.direction', 'above')
            ->assertJsonPath('data.reference_price', '2650.0000')
            ->json('data');

        self::assertSame(1, $index->sizes()['above']);

        // Second tick crosses the target: the sync queue delivers inline.
        $this->artisan('price:watch --once')->assertSuccessful();

        Notification::assertSentTimes(PriceAlertTriggered::class, 1);
        Notification::assertSentTo(
            User::query()->where('email', 'sara@example.com')->firstOrFail(),
            PriceAlertTriggered::class,
            fn (PriceAlertTriggered $notification): bool => $notification->alert->id === $alert['id']
                && $notification->quote->price->toDecimal() === '2701.2500'
                && $notification->toMail($notification->alert->user)->subject === 'Gold price alert: 2,700.00 USD/oz reached',
        );

        $this->assertDatabaseMissing('price_alerts', ['id' => $alert['id']]);
        $this->withHeaders($auth)->getJson("/api/alerts/{$alert['id']}")->assertNotFound();
        $this->withHeaders($auth)->getJson('/api/alerts')->assertOk()->assertJsonCount(0, 'data');
        self::assertSame(['above' => 0, 'below' => 0, 'inflight' => 0], $index->sizes());

        // Further ticks, above or back below the target, send nothing more.
        $this->artisan('price:watch --once')->assertSuccessful();
        $this->artisan('price:watch --once')->assertSuccessful();

        Notification::assertSentTimes(PriceAlertTriggered::class, 1);
    }
}
