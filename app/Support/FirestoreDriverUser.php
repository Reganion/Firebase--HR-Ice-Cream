<?php

namespace App\Support;

/**
 * Firestore `drivers` document as an object for API auth (session user).
 */
class FirestoreDriverUser
{
    public static function fromArray(?array $row): ?object
    {
        if (empty($row) || empty($row['id'])) {
            return null;
        }
        $o = new \stdClass();
        foreach ($row as $k => $v) {
            $o->$k = $v;
        }

        return $o;
    }

    /**
     * True when the request user is a Firestore-backed driver session (from `api.driver` middleware).
     */
    public static function isAuthenticatedUser(mixed $user): bool
    {
        return is_object($user) && isset($user->id) && (string) $user->id !== '';
    }
}
