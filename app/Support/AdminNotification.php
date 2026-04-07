<?php

namespace App\Support;

/**
 * Admin notification type strings and helpers for Firestore `admin_notifications`.
 */
class AdminNotification
{
    public const TYPE_ORDER_NEW = 'order_new';

    public const TYPE_ORDER_CREATED = 'order_created';

    public const TYPE_ORDER_DELIVERED = 'order_delivered';

    // Non-order admin notification types (kept for compatibility; not shown in the feed now).
    public const TYPE_PROFILE_UPDATE = 'profile_update';

    public const TYPE_ADDRESS_UPDATE = 'address_update';

    public const TYPE_ORDER_DRIVER_ACCEPTED = 'order_driver_accepted';

    public const TYPE_ORDER_OUT_FOR_DELIVERY = 'order_out_for_delivery';

    public const TYPE_DELIVERY_SUCCESS = 'delivery_success';

    /**
     * Types shown in the admin notification feed (navbar / API).
     *
     * @return list<string>
     */
    public static function adminFeedTypes(): array
    {
        return [
            self::TYPE_ORDER_NEW,
            self::TYPE_ORDER_CREATED,
            self::TYPE_ORDER_DELIVERED,
            self::TYPE_ORDER_DRIVER_ACCEPTED,
            self::TYPE_ORDER_OUT_FOR_DELIVERY,
            self::TYPE_DELIVERY_SUCCESS,
            'order_status',
        ];
    }

    /**
     * Apply extra business rules beyond `adminFeedTypes()`:
     * - Only show cancelled orders from `order_status`.
     *
     * @param array<string, mixed> $notification
     */
    public static function shouldShowInAdminFeed(array $notification): bool
    {
        $type = (string) ($notification['type'] ?? '');
        if (!in_array($type, self::adminFeedTypes(), true)) {
            return false;
        }

        if ($type === 'order_status') {
            $status = strtolower(trim((string) ($notification['data']['status'] ?? '')));
            return in_array($status, ['cancelled', 'canceled'], true);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $notification
     */
    public static function firestoreUserIdMatches(array $notification, mixed $adminId): bool
    {
        return (string) ($notification['user_id'] ?? '') === (string) $adminId;
    }
}
