<?php

declare(strict_types=1);

namespace App\Alerts\Exceptions;

use RuntimeException;

/** The alert index could not be made ready, so this tick cannot be matched. */
final class IndexUnavailable extends RuntimeException {}
