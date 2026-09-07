<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\PriceAlert;
use App\Models\User;
use App\Notifications\PriceAlertTriggered;
use App\Pricing\Price;
use App\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PriceAlertTriggeredTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_tells_the_user_the_new_price_and_the_level_they_watched(): void
    {
        $user = User::factory()->create(['name' => 'Sara']);
        $alert = PriceAlert::factory()->for($user)->above('2700')->create(['reference_price' => '2650.5']);
        $quote = new PriceQuote(Price::fromDecimal('2701.25'), CarbonImmutable::parse('2026-09-07 12:00:00', 'UTC'), 'fake');

        $mail = (new PriceAlertTriggered($alert, $quote))->toMail($user);
        $body = $mail->render()->toHtml();

        self::assertSame('Gold price alert: 2,700.00 USD/oz reached', $mail->subject);
        self::assertStringContainsString('Hello Sara,', $body);
        self::assertStringContainsString('rose to 2,701.25 USD/oz', $body);
        self::assertStringContainsString('2,700.00 USD/oz', $body);
        self::assertStringContainsString('2026-09-07 12:00:00 UTC', $body);
        self::assertStringContainsString('2,650.50 USD/oz when you created the alert', $body);
        self::assertStringContainsString('This alert has been removed', $body);
    }

    #[Test]
    public function it_describes_a_fall_for_below_alerts(): void
    {
        $alert = PriceAlert::factory()->below('2600')->create(['reference_price' => null]);
        $quote = new PriceQuote(Price::fromDecimal('2599'), CarbonImmutable::now(), 'fake');

        $body = (new PriceAlertTriggered($alert, $quote))->toMail($alert->user)->render()->toHtml();

        self::assertStringContainsString('fell to 2,599.00 USD/oz', $body);
        self::assertStringNotContainsString('when you created the alert', $body);
    }

    #[Test]
    public function it_exposes_the_facts_as_an_array(): void
    {
        $alert = PriceAlert::factory()->above('2700')->create();
        $quote = new PriceQuote(Price::fromDecimal('2701.25'), CarbonImmutable::parse('2026-09-07 12:00:00', 'UTC'), 'fake');

        self::assertSame([
            'alert_id' => $alert->id,
            'direction' => 'above',
            'target_price' => '2700.0000',
            'price' => '2701.2500',
            'observed_at' => '2026-09-07T12:00:00+00:00',
            'source' => 'fake',
        ], (new PriceAlertTriggered($alert, $quote))->toArray($alert->user));
    }
}
