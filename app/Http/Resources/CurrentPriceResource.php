<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Alerts\Index\CurrentPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CurrentPrice
 */
final class CurrentPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'price' => $this->quote->price->toDecimal(),
            'unit' => (string) config('gold.unit'),
            'source' => $this->quote->source,
            'observed_at' => $this->quote->observedAt->toIso8601String(),
            'received_at' => $this->receivedAt->toIso8601String(),
            'stale' => $this->isStale((int) config('gold.price_max_age_ms')),
        ];
    }
}
