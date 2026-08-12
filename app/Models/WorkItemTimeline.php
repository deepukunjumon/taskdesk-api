<?php

namespace App\Models;

use App\Enums\WorkItemStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkItemTimeline extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'work_item_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'assigned_to_name',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => WorkItemStatus::class,
            'to_status' => WorkItemStatus::class,
        ];
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
