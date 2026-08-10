<?php

use App\Enums\Priority;
use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use App\Services\WorkItemService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SlaSettingSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(SlaSettingSeeder::class);
});

/**
 * True multi-connection concurrency can't be simulated inside a single Pest
 * process/transaction, so this proves the sequencing logic itself is
 * collision-free across many rapid calls. The actual concurrency safety
 * comes from the `lockForUpdate()` row lock in
 * EloquentWorkItemRepository::nextWorkNumber(), which serializes concurrent
 * DB connections at the database level.
 */
it('generates unique, strictly sequential, zero-padded work IDs across many rapid creations', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);

    $service = app(WorkItemService::class);

    $workIds = collect(range(1, 15))->map(function () use ($service, $department, $employee, $admin) {
        $item = $service->create([
            'department_id' => $department->id,
            'entry_type' => 'task',
            'assigned_to_id' => $employee->id,
            'source' => 'internal',
            'priority' => Priority::Low->value,
            'subject' => 'Test task',
            'description' => 'Test description',
        ], $admin);

        return $item->work_id;
    });

    expect($workIds->unique())->toHaveCount(15);

    foreach ($workIds as $workId) {
        expect($workId)->toMatch('/^W\d{4}$/');
    }

    $numbers = $workIds->map(fn ($id) => (int) substr($id, 1))->sort()->values();

    for ($i = 1; $i < $numbers->count(); $i++) {
        expect($numbers[$i])->toBe($numbers[$i - 1] + 1);
    }
});

it('records a "created" timeline entry when a work item is created', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);

    $item = app(WorkItemService::class)->create([
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_by' => 'self',
        'assigned_to_id' => $employee->id,
        'source' => 'internal',
        'priority' => Priority::Medium->value,
        'subject' => 'Test task',
        'description' => 'Test description',
    ], $admin);

    $this->assertDatabaseHas('work_item_timelines', [
        'work_item_id' => $item->id,
        'action' => 'created',
        'to_status' => 'open',
    ]);
});

it('sets assigned_by to the creator when self-assigning', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $user->assignRole(Role::User->value);

    $response = $this->actingAs($user)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $user->id,
        'source' => 'internal',
        'priority' => Priority::Low->value,
        'subject' => 'Self task',
        'description' => 'assigned_by should be the actor, a real person',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.assigned_by.id', $user->id)
        ->assertJsonPath('data.assigned_by.name', $user->name);
});

it('sets assigned_by to the creator, not the assignee, when delegating to someone else', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);
    $employee->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $employee->id,
        'source' => 'internal',
        'priority' => Priority::Low->value,
        'subject' => 'Delegated task',
        'description' => 'assigned_by should be the admin who created it, not the employee',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.assigned_by.id', $admin->id)
        ->assertJsonPath('data.assigned_by.name', $admin->name)
        ->assertJsonPath('data.assigned_to.id', $employee->id);
});

it('ignores a client-supplied assigned_by_id — it is always computed from the actor', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);
    $employee->assignRole(Role::User->value);
    $impersonated = User::factory()->create();

    $response = $this->actingAs($admin)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $employee->id,
        'assigned_by_id' => $impersonated->id,
        'source' => 'internal',
        'priority' => Priority::Low->value,
        'subject' => 'Should ignore client-supplied assigned_by_id',
        'description' => 'assigned_by must always reflect the real actor',
    ]);

    $response->assertStatus(201)->assertJsonPath('data.assigned_by.id', $admin->id);
});
