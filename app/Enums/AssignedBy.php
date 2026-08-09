<?php

namespace App\Enums;

enum AssignedBy: string
{
    case SelfAssigned = 'self';
    case Manager = 'manager';
    case Ho = 'ho';
    case Branch = 'branch';
    case Client = 'client';
    case Vendor = 'vendor';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $value) => $value->value, self::cases());
    }
}
