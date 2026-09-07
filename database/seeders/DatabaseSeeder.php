<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Alerts\AlertStatus;
use App\Alerts\Direction;
use App\Models\PriceAlert;
use App\Models\User;
use App\Pricing\Price;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the demo data. Safe to run repeatedly: every record is looked up
     * before it is created, so restarting the stack never fails on duplicates.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo User', 'password' => Hash::make('password')],
        );

        // Two alerts far from the fake feed's starting price, so the list has
        // content but only the alert you create during the demo will fire.
        $this->alert($user, Direction::Below, Price::fromDecimal('2000'));
        $this->alert($user, Direction::Above, Price::fromDecimal('3500'));
    }

    private function alert(User $user, Direction $direction, Price $target): void
    {
        PriceAlert::query()->firstOrCreate(
            ['user_id' => $user->id, 'direction' => $direction->value, 'target_price' => $target->minor],
            ['target_price' => $target, 'reference_price' => Price::fromDecimal('2650'), 'status' => AlertStatus::Active],
        );
    }
}
