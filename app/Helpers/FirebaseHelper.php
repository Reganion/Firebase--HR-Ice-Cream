<?php

namespace App\Helpers;

/**
 * Convert Firebase result arrays to objects so Blade can use $item->property.
 */
class FirebaseHelper
{
    public static function toObject(?array $row): ?\stdClass
    {
        if ($row === null || $row === []) {
            return null;
        }
        return (object) $row;
    }

    /**
     * @param  array<int, array>  $rows
     * @return array<int, \stdClass>
     */
    public static function toObjects(array $rows): array
    {
        return array_map(fn (array $r) => (object) $r, $rows);
    }
}
