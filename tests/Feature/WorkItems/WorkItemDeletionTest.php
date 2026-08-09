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

it('soft-deletes a work item by setting its status, without removing the row', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $admin->id,
        'assigned_to_id' => $employee->id,
        'status' => WorkItemStatus::Open->value,
    ]);

    $response = $this->actingAs($admin)->deleteJson("/api/work-items/{$item->id}");

    $response->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseHas('work_items', [
        'id' => $item->id,
        'status' => WorkItemStatus::Deleted->value,
    ]);

    $this->assertDatabaseHas('work_item_timelines', [
        'work_item_id' => $item->id,
        'action' => 'deleted',
        'to_status' => WorkItemStatus::Deleted->value,
    ]);
});

it('excludes a deleted work item from the index listing', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $admin->id,
        'assigned_to_id' => $employee->id,
        'status' => WorkItemStatus::Open->value,
    ]);

    $this->actingAs($admin)->deleteJson("/api/work-items/{$item->id}")->assertOk();

    $response = $this->actingAs($admin)->getJson('/api/work-items');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->not->toContain($item->id);
});

it('prevents an employee from deleting a work item', function () {
    $department = Department::factory()->create();
    $employee = User::factory()->create(['department_id' => $department->id]);
    $employee->assignRole(Role::Employee->value);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $employee->id,
        'assigned_to_id' => $employee->id,
        'status' => WorkItemStatus::Open->value,
    ]);

    $response = $this->actingAs($employee)->deleteJson("/api/work-items/{$item->id}");

    $response->assertStatus(403);
    $this->assertDatabaseHas('work_items', ['id' => $item->id, 'status' => WorkItemStatus::Open->value]);
});

it("prevents an admin from deleting another department's work item", function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $adminA = User::factory()->create(['department_id' => $deptA->id]);
    $adminA->assignRole(Role::Admin->value);
    $employeeB = User::factory()->create(['department_id' => $deptB->id]);

    $item = WorkItem::factory()->create([
        'department_id' => $deptB->id,
        'created_by_id' => $employeeB->id,
        'assigned_to_id' => $employeeB->id,
        'status' => WorkItemStatus::Open->value,
    ]);

    $response = $this->actingAs($adminA)->deleteJson("/api/work-items/{$item->id}");

    $response->assertStatus(403);
});

it('rejects deleting a work item that is already deleted', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $admin->id,
        'assigned_to_id' => $employee->id,
        'status' => WorkItemStatus::Deleted->value,
    ]);

    $response = $this->actingAs($admin)->deleteJson("/api/work-items/{$item->id}");

    $response->assertStatus(403);
});

it('allows a superadmin to delete a work item in any department', function () {
    $department = Department::factory()->create();
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::SuperAdmin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $employee->id,
        'assigned_to_id' => $employee->id,
        'status' => WorkItemStatus::Open->value,
    ]);

    $response = $this->actingAs($superAdmin)->deleteJson("/api/work-items/{$item->id}");

    $response->assertOk();
    expect($item->fresh()->status)->toBe(WorkItemStatus::Deleted);
});
