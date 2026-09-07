<?php

declare(strict_types=1);

namespace App\Models;

use App\Alerts\AlertStatus;
use App\Alerts\Direction;
use App\Pricing\Casts\AsPrice;
use App\Pricing\Price;
use Carbon\CarbonImmutable;
use Database\Factories\PriceAlertFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property Direction $direction
 * @property Price $target_price
 * @property Price|null $reference_price
 * @property AlertStatus $status
 * @property Price|null $triggered_price
 * @property CarbonImmutable|null $triggered_at
 * @property int $attempts
 * @property string|null $last_error
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
class PriceAlert extends Model
{
    /** @use HasFactory<PriceAlertFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'active',
        'attempts' => 0,
    ];

    protected $fillable = [
        'user_id',
        'direction',
        'target_price',
        'reference_price',
        'status',
        'triggered_price',
        'triggered_at',
        'attempts',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => Direction::class,
            'status' => AlertStatus::class,
            'target_price' => AsPrice::class,
            'reference_price' => AsPrice::class,
            'triggered_price' => AsPrice::class,
            'triggered_at' => 'immutable_datetime',
            'attempts' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<PriceAlert>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', AlertStatus::Active);
    }

    /**
     * @param  Builder<PriceAlert>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->getKey());
    }

    public function isActive(): bool
    {
        return $this->status === AlertStatus::Active;
    }

    /** Whether the given price satisfies this alert's level and side. */
    public function isHitBy(Price $price): bool
    {
        return $this->direction->isHit($this->target_price, $price);
    }
}
