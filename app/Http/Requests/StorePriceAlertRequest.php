<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Alerts\Direction;
use App\Pricing\Price;
use App\Pricing\Rules\ValidPrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePriceAlertRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'target_price' => ['required', 'numeric', 'gt:0', 'decimal:0,'.Price::SCALE, 'max:99999999999', new ValidPrice],
            'direction' => ['nullable', 'string', Rule::enum(Direction::class)],
        ];
    }

    public function target(): Price
    {
        return Price::fromDecimal((string) $this->validated('target_price'));
    }

    public function direction(): ?Direction
    {
        $direction = $this->validated('direction');

        return is_string($direction) ? Direction::from($direction) : null;
    }
}
