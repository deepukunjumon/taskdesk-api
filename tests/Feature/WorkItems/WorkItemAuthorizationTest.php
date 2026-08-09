<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkItem;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it("prevents an employee from viewing another employee's work item", function () {
    $department = Department::factory()->create();
    $owner = User::factory()->create(['department_id' => $department->id]);
    $owner->assignRole(Role::Employee->value);
    $other = User::factory()->create(['department_id' => $department->id]);
    $other->assignRole(Role::Employee->value);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $owner->id,
        'assigned_to_id' => $owner->id,
    ]);

    $this->actingAs($other)->getJson("/api/work-items/{$item->id}")->assertStatus(403);
});

it("prevents an employee from updating another employee's work item", function () {
    $department = Department::factory()->create();
    $owner = User::factory()->create(['department_id' => $department->id]);
    $owner->assignRole(Role::Employee->value);
    $other = User::factory()->create(['department_id' => $department->id]);
    $other->assignRole(Role::Employee->value);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $owner->id,
        'assigned_to_id' => $owner->id,
    ]);

    $response = $this->actingAs($other)->patchJson("/api/work-items/{$item->id}", [
        'remarks' => 'Trying to sneak in an update',
    ]);

    $response->assertStatus(403);
});

it('prevents an employee from reassigning a work item', function () {
    $department = Department::factory()->create();
    $employee = User::factory()->create(['department_id' => $department->id]);
    $employee->assignRole(Role::Employee->value);
    $colleague = User::factory()->create(['department_id' => $department->id]);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $employee->id,
        'assigned_to_id' => $employee->id,
    ]);

    $response = $this->actingAs($employee)->patchJson("/api/work-items/{$item->id}/reassign", [
        'assigned_to_id' => $colleague->id,
    ]);

    $response->assertStatus(403);
});

it("prevents an admin from viewing another department's work item", function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $adminA = User::factory()->create(['department_id' => $deptA->id]);
    $adminA->assignRole(Role::Admin->value);
    $employeeB = User::factory()->create(['department_id' => $deptB->id]);

    $item = WorkItem::factory()->create([
        'department_id' => $deptB->id,
        'created_by_id' => $employeeB->id,
        'assigned_to_id' => $employeeB->id,
    ]);

    $this->actingAs($adminA)->getJson("/api/work-items/{$item->id}")->assertStatus(403);
});

it("prevents an admin from creating a work item in another department", function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $adminA = User::factory()->create(['department_id' => $deptA->id]);
    $adminA->assignRole(Role::Admin->value);
    $employeeB = User::factory()->create(['department_id' => $deptB->id]);

    $response = $this->actingAs($adminA)->postJson('/api/work-items', [
        'department_id' => $deptB->id,
        'entry_type' => 'task',
        'assigned_by' => 'self',
        'assigned_to_id' => $employeeB->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Cross department attempt',
        'description' => 'Should be rejected',
    ]);

    $response->assertStatus(422);
});

it("excludes another department's work items from an admin's index listing", function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $adminA = User::factory()->create(['department_id' => $deptA->id]);
    $adminA->assignRole(Role::Admin->value);
    $employeeA = User::factory()->create(['department_id' => $deptA->id]);
    $employeeB = User::factory()->create(['department_id' => $deptB->id]);

    $itemA = WorkItem::factory()->create([
        'department_id' => $deptA->id,
        'created_by_id' => $adminA->id,
        'assigned_to_id' => $employeeA->id,
    ]);
    $itemB = WorkItem::factory()->create([
        'department_id' => $deptB->id,
        'created_by_id' => $employeeB->id,
        'assigned_to_id' => $employeeB->id,
    ]);

    $response = $this->actingAs($adminA)->getJson('/api/work-items');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($itemA->id);
    expect($ids)->not->toContain($itemB->id);
});

it('only shows an employee their own assigned work items in the index listing', function () {
    $department = Department::factory()->create();
    $employee = User::factory()->create(['department_id' => $department->id]);
    $employee->assignRole(Role::Employee->value);
    $colleague = User::factory()->create(['department_id' => $department->id]);

    $ownItem = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $colleague->id,
        'assigned_to_id' => $employee->id,
    ]);
    $othersItem = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $colleague->id,
        'assigned_to_id' => $colleague->id,
    ]);

    $response = $this->actingAs($employee)->getJson('/api/work-items');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($ownItem->id);
    expect($ids)->not->toContain($othersItem->id);
});

it('allows a superadmin to view work items across all departments', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::SuperAdmin->value);
    $employeeA = User::factory()->create(['department_id' => $deptA->id]);
    $employeeB = User::factory()->create(['department_id' => $deptB->id]);

    WorkItem::factory()->create([
        'department_id' => $deptA->id,
        'created_by_id' => $employeeA->id,
        'assigned_to_id' => $employeeA->id,
    ]);
    WorkItem::factory()->create([
        'department_id' => $deptB->id,
        'created_by_id' => $employeeB->id,
        'assigned_to_id' => $employeeB->id,
    ]);

    $response = $this->actingAs($superAdmin)->getJson('/api/work-items');

    $response->assertOk();
    expect($response->json('meta.total') ?? count($response->json('data')))->toBeGreaterThanOrEqual(2);
});
