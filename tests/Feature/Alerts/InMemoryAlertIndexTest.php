<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Alerts\Contracts\AlertIndex;
use App\Alerts\Index\InMemoryAlertIndex;

final class InMemoryAlertIndexTest extends AlertIndexContractTestCase
{
    protected function makeIndex(): AlertIndex
    {
        return new InMemoryAlertIndex;
    }
}
