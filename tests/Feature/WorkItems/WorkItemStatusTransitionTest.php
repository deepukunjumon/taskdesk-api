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

it('rejects an invalid status transition from pending directly to open', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $admin->id,
        'assigned_to_id' => $employee->id,
        'status' => WorkItemStatus::Pending->value,
        'start_time' => now(),
    ]);

    $response = $this->actingAs($admin)->patchJson("/api/work-items/{$item->id}/status", [
        'status' => WorkItemStatus::Open->value,
    ]);

    $response->assertStatus(422);
    expect($item->fresh()->status)->toBe(WorkItemStatus::Pending);
});

it('allows closing a work item directly from open, for tasks completed and logged late', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $admin->id,
        'assigned_to_id' => $employee->id,
        'status' => WorkItemStatus::Open->value,
        'start_time' => null,
    ]);

    $response = $this->actingAs($admin)->patchJson("/api/work-items/{$item->id}/status", [
        'status' => WorkItemStatus::Closed->value,
        'resolution' => 'Already done before this was logged.',
    ]);

    $response->assertOk();
    $item->refresh();
    expect($item->status)->toBe(WorkItemStatus::Closed);
    expect($item->start_time)->not->toBeNull();
    expect($item->end_time)->not->toBeNull();

    $this->assertDatabaseHas('work_item_timelines', [
        'work_item_id' => $item->id,
        'action' => 'status_changed',
        'from_status' => WorkItemStatus::Open->value,
        'to_status' => WorkItemStatus::Closed->value,
    ]);
});

it('walks a valid transition chain from open to in_progress to closed with resolution', function () {
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

    $this->actingAs($admin)->patchJson("/api/work-items/{$item->id}/status", [
        'status' => WorkItemStatus::InProgress->value,
    ])->assertOk();

    expect($item->fresh()->start_time)->not->toBeNull();

    $response = $this->actingAs($admin)->patchJson("/api/work-items/{$item->id}/status", [
        'status' => WorkItemStatus::Closed->value,
        'resolution' => 'Fixed the issue.',
    ]);

    $response->assertOk();
    $item->refresh();
    expect($item->status)->toBe(WorkItemStatus::Closed);
    expect($item->end_time)->not->toBeNull();
    expect($item->resolution)->toBe('Fixed the issue.');
});

it('rejects closing a work item without a resolution', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employee = User::factory()->create(['department_id' => $department->id]);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $admin->id,
        'assigned_to_id' => $employee->id,
        'status' => WorkItemStatus::InProgress->value,
        'start_time' => now(),
    ]);

    $response = $this->actingAs($admin)->patchJson("/api/work-items/{$item->id}/status", [
        'status' => WorkItemStatus::Closed->value,
    ]);

    $response->assertStatus(422);
    expect($item->fresh()->status)->toBe(WorkItemStatus::InProgress);
});

it('writes a timeline row for every status change', function () {
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

    $this->actingAs($admin)->patchJson("/api/work-items/{$item->id}/status", [
        'status' => WorkItemStatus::InProgress->value,
        'note' => 'Starting work',
    ])->assertOk();

    $this->assertDatabaseHas('work_item_timelines', [
        'work_item_id' => $item->id,
        'action' => 'status_changed',
        'from_status' => WorkItemStatus::Open->value,
        'to_status' => WorkItemStatus::InProgress->value,
        'note' => 'Starting work',
    ]);
});

it('writes a reassigned timeline row through the dedicated reassign endpoint', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $department->id]);
    $admin->assignRole(Role::Admin->value);
    $employeeA = User::factory()->create(['department_id' => $department->id]);
    $employeeB = User::factory()->create(['department_id' => $department->id]);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $admin->id,
        'assigned_to_id' => $employeeA->id,
    ]);

    $response = $this->actingAs($admin)->patchJson("/api/work-items/{$item->id}/reassign", [
        'assigned_to_id' => $employeeB->id,
    ]);

    $response->assertOk();
    expect($item->fresh()->assigned_to_id)->toBe($employeeB->id);

    $this->assertDatabaseHas('work_item_timelines', [
        'work_item_id' => $item->id,
        'action' => 'reassigned',
    ]);
});
