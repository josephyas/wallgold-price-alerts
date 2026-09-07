<?php

declare(strict_types=1);

namespace Tests\Feature\Flow;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Index\InMemoryAlertIndex;

final class AlertDeliveryFlowTest extends AlertDeliveryFlowTestCase
{
    protected function index(): AlertIndex
    {
        return new InMemoryAlertIndex;
    }
}
