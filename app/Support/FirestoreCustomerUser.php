<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Maps a Firestore `customers` document array to a stdClass usable by the API
 * (Carbon for otp_expires_at / email_verified_at).
 */
class FirestoreCustomerUser
{
    public static function fromArray(?array $row): ?object
    {
        if (empty($row) || empty($row['id'])) {
            return null;
        }
        $o = new \stdClass();
        foreach ($row as $k => $v) {
            if ($k === 'otp_expires_at' || $k === 'email_verified_at') {
                continue;
            }
            $o->$k = $v;
        }
        $o->email_verified_at = ! empty($row['email_verified_at'])
            ? Carbon::parse((string) $row['email_verified_at'])
            : null;
        $o->otp_expires_at = ! empty($row['otp_expires_at'])
            ? Carbon::parse((string) $row['otp_expires_at'])
            : null;

        return $o;
    }

    public static function isEmailVerified(object $customer): bool
    {
        return $customer->email_verified_at !== null;
    }
}
