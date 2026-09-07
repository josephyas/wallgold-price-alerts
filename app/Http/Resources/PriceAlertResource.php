<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Alerts\AlertStatus;
use App\Models\PriceAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceAlert
 */
final class PriceAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_price' => $this->target_price->toDecimal(),
            'direction' => $this->direction->value,
            'status' => $this->status->value,
            'reference_price' => $this->reference_price?->toDecimal(),
            'triggered_price' => $this->triggered_price?->toDecimal(),
            'triggered_at' => $this->triggered_at?->toIso8601String(),
            'attempts' => $this->attempts,
            'last_error' => $this->when($this->status === AlertStatus::Failed, $this->last_error),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
