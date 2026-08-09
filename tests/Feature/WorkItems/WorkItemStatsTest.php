<?php

use App\Enums\Role;
use App\Enums\WorkItemStatus;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkItem;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('returns status-bucketed counts scoped to the admin\'s own department', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $adminA = User::factory()->create(['department_id' => $deptA->id]);
    $adminA->assignRole(Role::Admin->value);
    $employeeA = User::factory()->create(['department_id' => $deptA->id]);
    $employeeB = User::factory()->create(['department_id' => $deptB->id]);

    WorkItem::factory()->create([
        'department_id' => $deptA->id,
        'created_by_id' => $adminA->id,
        'assigned_to_id' => $employeeA->id,
        'status' => WorkItemStatus::Open->value,
    ]);
    WorkItem::factory()->create([
        'department_id' => $deptA->id,
        'created_by_id' => $adminA->id,
        'assigned_to_id' => $employeeA->id,
        'status' => WorkItemStatus::InProgress->value,
        'start_time' => now(),
    ]);
    WorkItem::factory()->create([
        'department_id' => $deptA->id,
        'created_by_id' => $adminA->id,
        'assigned_to_id' => $employeeA->id,
        'status' => WorkItemStatus::Closed->value,
        'start_time' => now(),
        'end_time' => now(),
        'resolution' => 'done',
    ]);
    // Belongs to a different department — must not be counted for adminA.
    WorkItem::factory()->create([
        'department_id' => $deptB->id,
        'created_by_id' => $employeeB->id,
        'assigned_to_id' => $employeeB->id,
        'status' => WorkItemStatus::Open->value,
    ]);

    $response = $this->actingAs($adminA)->getJson('/api/work-items/stats');

    $response->assertOk();
    $response->assertJson([
        'data' => [
            'total' => 3,
            'open' => 1,
            'in_progress' => 1,
            'pending' => 0,
            'closed' => 1,
        ],
    ]);
});

it('counts an overdue item whose sla_due_at is in the past and not closed', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);

    WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $admin->id,
        'assigned_to_id' => $employee->id,
        'status' => WorkItemStatus::Open->value,
        'sla_due_at' => now()->subDay(),
    ]);
    WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $admin->id,
        'assigned_to_id' => $employee->id,
        'status' => WorkItemStatus::Open->value,
        'sla_due_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($admin)->getJson('/api/work-items/stats');

    $response->assertOk()->assertJsonPath('data.overdue', 1);
});

it('excludes deleted items from every count', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);

    WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $admin->id,
        'assigned_to_id' => $employee->id,
        'status' => WorkItemStatus::Deleted->value,
    ]);

    $response = $this->actingAs($admin)->getJson('/api/work-items/stats');

    $response->assertOk()->assertJsonPath('data.total', 0);
});

it("only counts an employee's own assigned items", function () {
    $department = Department::factory()->create();
    $employee = User::factory()->create(['department_id' => $department->id]);
    $employee->assignRole(Role::Employee->value);
    $colleague = User::factory()->create(['department_id' => $department->id]);

    WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $colleague->id,
        'assigned_to_id' => $employee->id,
        'status' => WorkItemStatus::Open->value,
    ]);
    WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $colleague->id,
        'assigned_to_id' => $colleague->id,
        'status' => WorkItemStatus::Open->value,
    ]);

    $response = $this->actingAs($employee)->getJson('/api/work-items/stats');

    $response->assertOk()->assertJsonPath('data.total', 1);
});
