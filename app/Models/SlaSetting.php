<?php

namespace App\Models;

use App\Enums\Priority;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SlaSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'priority',
        'hours',
    ];

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'hours' => 'integer',
        ];
    }
}
