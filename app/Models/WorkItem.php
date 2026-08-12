<?php

namespace App\Models;

use App\Enums\EntryType;
use App\Enums\Priority;
use App\Enums\Source;
use App\Enums\WorkItemStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'task_id',
        'department_id',
        'entry_type',
        'assigned_by_id',
        'assigned_to_id',
        'created_by_id',
        'source',
        'branch_id',
        'category_id',
        'priority',
        'subject',
        'description',
        'status',
        'start_time',
        'end_time',
        'resolution',
        'remarks',
        'sla_due_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => EntryType::class,
            'source' => Source::class,
            'priority' => Priority::class,
            'status' => WorkItemStatus::class,
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'sla_due_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(WorkItemTimeline::class)->orderBy('created_at');
    }
}
