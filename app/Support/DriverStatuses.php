<?php

namespace App\Support;

/**
 * Status values stored on Firestore `drivers` documents (same strings as legacy SQL enum).
 */
final class DriverStatuses
{
    public const AVAILABLE = 'available';

    public const ON_ROUTE = 'on_route';

    public const OFF_DUTY = 'off_duty';

    public const DEACTIVATE = 'deactivate';

    public const ARCHIVE = 'archive';
}
