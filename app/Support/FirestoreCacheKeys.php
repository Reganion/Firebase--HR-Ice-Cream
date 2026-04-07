<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Central keys + invalidation for Firestore list caches (reduces repeated full-collection reads).
 */
final class FirestoreCacheKeys
{
    public const LIST_PREFIX = 'fs:v1:all:';

    public const API_BEST_SELLERS = 'api:v1:resp:best-sellers';

    public const API_POPULAR = 'api:v1:resp:popular';

    public const API_FLAVORS_INDEX = 'api:v1:resp:flavors-index';

    public const API_GALLONS_INDEX = 'api:v1:resp:gallons-index';

    public const SUPERADMIN_HAS_ANY = 'superadmin:v1:has-any';

    /** Single admin document (Firestore `admins/{id}`) — avoids repeated reads on every admin view. */
    public const ADMIN_DOC_PREFIX = 'fs:v1:admin:doc:';

    /** Per-admin notification list query (`whereAdminNotificationsForUser`) — two Firestore reads per miss without cache. */
    public const ADMIN_NOTIF_FEED_PREFIX = 'fs:v1:admin:notif-feed:';

    public static function adminDocKey(string $adminId): string
    {
        return self::ADMIN_DOC_PREFIX.$adminId;
    }

    public static function adminDocByEmailKey(string $email): string
    {
        return self::ADMIN_DOC_PREFIX.'email:'.md5(strtolower(trim($email)));
    }

    public static function forgetAdminDoc(string $adminId): void
    {
        if ($adminId === '') {
            return;
        }
        Cache::forget(self::adminDocKey($adminId));
    }

    public static function forgetAdminDocByEmail(?string $email): void
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return;
        }
        Cache::forget(self::adminDocByEmailKey($email));
    }

    public static function adminNotificationsFeedKey(string $adminId): string
    {
        return self::ADMIN_NOTIF_FEED_PREFIX.$adminId;
    }

    public static function forgetAdminNotificationsFeed(string $adminId): void
    {
        if ($adminId === '') {
            return;
        }
        Cache::forget(self::adminNotificationsFeedKey($adminId));
    }

    public static function listKey(string $collection): string
    {
        return self::LIST_PREFIX.$collection;
    }

    public static function forgetList(string $collection): void
    {
        Cache::forget(self::listKey($collection));
    }

    public static function invalidateOrders(): void
    {
        self::forgetList('orders');
        Cache::forget(self::API_BEST_SELLERS);
        Cache::forget(self::API_POPULAR);
    }

    public static function invalidateFlavors(): void
    {
        self::forgetList('flavors');
        Cache::forget(self::API_BEST_SELLERS);
        Cache::forget(self::API_POPULAR);
        Cache::forget(self::API_FLAVORS_INDEX);
    }

    public static function invalidateGallons(): void
    {
        self::forgetList('gallons');
        Cache::forget(self::API_GALLONS_INDEX);
    }

    public static function invalidateFeedback(): void
    {
        self::forgetList('feedback');
        Cache::forget(self::API_POPULAR);
    }

    public static function invalidateIngredients(): void
    {
        self::forgetList('ingredients');
    }

    public static function invalidateCustomers(): void
    {
        self::forgetList('customers');
        self::forgetList('customer_addresses');
    }

    public static function invalidateDrivers(): void
    {
        self::forgetList('drivers');
    }

    public static function invalidateChat(): void
    {
        self::forgetList('chat_messages');
    }
}
