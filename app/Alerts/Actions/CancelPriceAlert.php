<?php

declare(strict_types=1);

namespace App\Alerts\Actions;

use App\Alerts\AlertStatus;
use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Exceptions\AlertBeingDelivered;
use App\Models\PriceAlert;

class CancelPriceAlert
{
    public function __construct(private readonly AlertIndex $index) {}

    /**
     * @throws AlertBeingDelivered
     */
    public function cancel(PriceAlert $alert): void
    {
        // The conditional delete is the guard: a worker that has claimed the
        // delivery keeps the row until it is done.
        $deleted = PriceAlert::query()
            ->whereKey($alert->id)
            ->where('status', '!=', AlertStatus::Sending)
            ->delete();

        if ($deleted === 0) {
            throw new AlertBeingDelivered;
        }

        // A member that lingers here is harmless: the delivery job finds no row and acknowledges it.
        $this->index->remove($alert->id);
    }
}
