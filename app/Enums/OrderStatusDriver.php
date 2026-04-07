<?php

namespace App\Enums;

enum OrderStatusDriver: string
{
    case Pending = 'Pending';
    case Accepted = 'Accepted';
    case Completed = 'Completed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
