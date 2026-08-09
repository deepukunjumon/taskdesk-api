<?php

namespace Database\Factories;

use App\Enums\AssignedBy;
use App\Enums\EntryType;
use App\Enums\Priority;
use App\Enums\Source;
use App\Enums\WorkItemStatus;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkItem>
 */
class WorkItemFactory extends Factory
{
    protected $model = WorkItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_id' => 'W'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 4, '0', STR_PAD_LEFT),
            'department_id' => Department::factory(),
            'entry_type' => fake()->randomElement(EntryType::values()),
            'assigned_by' => fake()->randomElement(AssignedBy::values()),
            'assigned_to_id' => User::factory(),
            'created_by_id' => User::factory(),
            'source' => fake()->randomElement(Source::values()),
            'priority' => fake()->randomElement(Priority::values()),
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'status' => WorkItemStatus::Open->value,
        ];
    }
}
