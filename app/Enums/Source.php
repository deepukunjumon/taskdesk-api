<?php

namespace App\Enums;

enum Source: string
{
    case BranchClient = 'branch_client';
    case Internal = 'internal';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $source) => $source->value, self::cases());
    }
}
