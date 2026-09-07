<?php

declare(strict_types=1);

namespace App\Pricing\Casts;

use App\Pricing\Price;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps an integer minor-units column to a Price value object and back.
 *
 * @implements CastsAttributes<Price, Price|string|int|float>
 */
final class AsPrice implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Price
    {
        return $value === null ? null : Price::fromMinor((int) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return match (true) {
            $value === null => null,
            $value instanceof Price => $value->minor,
            default => Price::fromDecimal($value)->minor,
        };
    }
}
