<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Alerts\AlertStatus;
use App\Alerts\Direction;
use App\Models\PriceAlert;
use App\Models\User;
use App\Pricing\Price;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceAlert>
 */
final class PriceAlertFactory extends Factory
{
    protected $model = PriceAlert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'direction' => Direction::Above,
            'target_price' => Price::fromDecimal(fake()->numberBetween(2_700_00, 2_900_00) / 100),
            'reference_price' => Price::fromDecimal('2650.00'),
            'status' => AlertStatus::Active,
        ];
    }

    public function above(string $target): self
    {
        return $this->state(['direction' => Direction::Above, 'target_price' => Price::fromDecimal($target)]);
    }

    public function below(string $target): self
    {
        return $this->state(['direction' => Direction::Below, 'target_price' => Price::fromDecimal($target)]);
    }

    public function sending(): self
    {
        return $this->state(['status' => AlertStatus::Sending, 'attempts' => 1]);
    }

    public function failed(): self
    {
        return $this->state(['status' => AlertStatus::Failed, 'attempts' => 3, 'last_error' => 'Mailer refused the message.']);
    }
}
