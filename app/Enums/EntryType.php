<?php

namespace App\Enums;

enum EntryType: string
{
    case Task = 'task';
    case SupportCall = 'support_call';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
