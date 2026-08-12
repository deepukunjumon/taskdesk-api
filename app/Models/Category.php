<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * A category attached to zero departments is a common one (e.g.
     * "General") that applies regardless of which department is selected —
     * see CategoryController::index() for the matching query-side rule.
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'category_department');
    }
}
