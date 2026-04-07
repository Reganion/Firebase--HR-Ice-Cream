<?php

namespace App\Support;

/**
 * Customer notification type strings for Firestore `customer_notifications`.
 */
class CustomerNotification
{
    public const TYPE_ORDER_PLACED = 'order_placed';

    public const TYPE_ORDER_STATUS = 'order_status';

    public const TYPE_ORDER_DELIVERED = 'order_delivered';
}
