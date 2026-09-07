<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PriceAlert;
use App\Models\User;
use App\Pricing\PriceQuote;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the user the price they were watching has been reached. Sent
 * synchronously by the delivery job, which owns retries and ordering.
 */
final class PriceAlertTriggered extends Notification
{
    public function __construct(
        public readonly PriceAlert $alert,
        public readonly PriceQuote $quote,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $unit = (string) config('gold.unit');
        $target = $this->alert->target_price->format();
        $price = $this->quote->price->format();
        $reference = $this->alert->reference_price;

        return (new MailMessage)
            ->subject(sprintf('Gold price alert: %s %s reached', $target, $unit))
            ->greeting($notifiable instanceof User ? sprintf('Hello %s,', $notifiable->name) : 'Hello,')
            ->line(sprintf('The gold price %s %s %s, reaching the level you were watching: %s %s.', $this->alert->direction->movement(), $price, $unit, $target, $unit))
            ->line(sprintf('Observed at %s UTC.', $this->quote->observedAt->utc()->toDateTimeString()))
            ->lineIf($reference !== null, sprintf('The price was %s %s when you created the alert.', $reference?->format(), $unit))
            ->line('This alert has been removed. You can create a new one at any time.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'direction' => $this->alert->direction->value,
            'target_price' => $this->alert->target_price->toDecimal(),
            'price' => $this->quote->price->toDecimal(),
            'observed_at' => $this->quote->observedAt->toIso8601String(),
            'source' => $this->quote->source,
        ];
    }
}
