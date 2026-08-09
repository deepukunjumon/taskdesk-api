<?php

namespace App\Enums;

enum BranchType: string
{
    case Branch = 'branch';
    case Client = 'client';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
