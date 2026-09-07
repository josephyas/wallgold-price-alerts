<?php

declare(strict_types=1);

namespace App\Alerts\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** The alert is being emailed right now, so it can no longer be cancelled. */
final class AlertBeingDelivered extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('This alert is being delivered and can no longer be cancelled.');
    }
}
