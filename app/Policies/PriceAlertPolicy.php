<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PriceAlert;
use App\Models\User;

final class PriceAlertPolicy
{
    public function view(User $user, PriceAlert $alert): bool
    {
        return $alert->user_id === $user->id;
    }

    public function delete(User $user, PriceAlert $alert): bool
    {
        return $alert->user_id === $user->id;
    }
}
