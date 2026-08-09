<?php

namespace App\Models;

use App\Enums\BranchType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'code',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => BranchType::class,
        ];
    }
}
