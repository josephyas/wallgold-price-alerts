<?php

declare(strict_types=1);

namespace App\Alerts;

/**
 * Lifecycle of a stored alert. Delivered alerts are deleted, so there is no
 * "sent" state: a row exists only while the alert still has work to do.
 */
enum AlertStatus: string
{
    /** Waiting for the price to reach the target. */
    case Active = 'active';

    /** Claimed by a worker that is sending the email right now. */
    case Sending = 'sending';

    /** Every delivery attempt failed; kept so the user can see and remove it. */
    case Failed = 'failed';
}
