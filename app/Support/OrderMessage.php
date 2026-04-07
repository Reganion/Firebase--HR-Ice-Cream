<?php

namespace App\Support;

/**
 * Field values for Firestore `order_messages`.
 */
class OrderMessage
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVE = 'archive';

    public const CUSTOMER_STATUS_ACTIVE = 'active';

    public const CUSTOMER_STATUS_ARCHIVE = 'archive';

    public const SENDER_DRIVER = 'driver';

    public const SENDER_CUSTOMER = 'customer';
}
