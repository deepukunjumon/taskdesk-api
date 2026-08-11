<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkItem;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it("prevents a user from viewing another user's work item", function () {
    $department = Department::factory()->create();
    $owner = User::factory()->create(['department_id' => $department->id]);
    $owner->assignRole(Role::User->value);
    $other = User::factory()->create(['department_id' => $department->id]);
    $other->assignRole(Role::User->value);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $owner->id,
        'assigned_to_id' => $owner->id,
    ]);

    $this->actingAs($other)->getJson("/api/work-items/{$item->id}")->assertStatus(403);
});

it("prevents a user from updating another user's work item", function () {
    $department = Department::factory()->create();
    $owner = User::factory()->create(['department_id' => $department->id]);
    $owner->assignRole(Role::User->value);
    $other = User::factory()->create(['department_id' => $department->id]);
    $other->assignRole(Role::User->value);

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

it('prevents an unrelated user from reassigning a work item', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $user->assignRole(Role::User->value);
    $colleague = User::factory()->create(['department_id' => $department->id]);
    $colleague->assignRole(Role::User->value);

    $item = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $user->id,
        'assigned_to_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->patchJson("/api/work-items/{$item->id}/reassign", [
        'assigned_to_id' => $colleague->id,
    ]);

    $response->assertStatus(403);
});

it('allows an admin to view a work item in any department', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $adminA = User::factory()->create(['department_id' => $deptA->id]);
    $adminA->assignRole(Role::Admin->value);
    $userB = User::factory()->create(['department_id' => $deptB->id]);
    $userB->assignRole(Role::User->value);

    $item = WorkItem::factory()->create([
        'department_id' => $deptB->id,
        'created_by_id' => $userB->id,
        'assigned_to_id' => $userB->id,
    ]);

    $this->actingAs($adminA)->getJson("/api/work-items/{$item->id}")->assertOk();
});

it('allows an admin to create a work item assigned to a user in any department', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $adminA = User::factory()->create(['department_id' => $deptA->id]);
    $adminA->assignRole(Role::Admin->value);
    $userB = User::factory()->create(['department_id' => $deptB->id]);
    $userB->assignRole(Role::User->value);

    $response = $this->actingAs($adminA)->postJson('/api/work-items', [
        'department_id' => $deptB->id,
        'entry_type' => 'task',
        'assigned_to_id' => $userB->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Cross department task',
        'description' => 'Admin authorization is global, not department-scoped',
    ]);

    $response->assertStatus(201);
});

it("includes work items from every department in an admin's index listing", function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $adminA = User::factory()->create(['department_id' => $deptA->id]);
    $adminA->assignRole(Role::Admin->value);
    $userA = User::factory()->create(['department_id' => $deptA->id]);
    $userB = User::factory()->create(['department_id' => $deptB->id]);

    $itemA = WorkItem::factory()->create([
        'department_id' => $deptA->id,
        'created_by_id' => $adminA->id,
        'assigned_to_id' => $userA->id,
    ]);
    $itemB = WorkItem::factory()->create([
        'department_id' => $deptB->id,
        'created_by_id' => $userB->id,
        'assigned_to_id' => $userB->id,
    ]);

    $response = $this->actingAs($adminA)->getJson('/api/work-items');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($itemA->id);
    expect($ids)->toContain($itemB->id);
});

it('only shows a user their own assigned work items in the index listing', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $user->assignRole(Role::User->value);
    $colleague = User::factory()->create(['department_id' => $department->id]);

    $ownItem = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $colleague->id,
        'assigned_to_id' => $user->id,
    ]);
    $othersItem = WorkItem::factory()->create([
        'department_id' => $department->id,
        'created_by_id' => $colleague->id,
        'assigned_to_id' => $colleague->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/work-items');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($ownItem->id);
    expect($ids)->not->toContain($othersItem->id);
});

it('shows a manager a task in their own index listing after assigning it to a report', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $department->id]);
    $manager->assignRole(Role::User->value);
    $report = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $report->assignRole(Role::User->value);

    $createResponse = $this->actingAs($manager)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $report->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Delegated to report',
        'description' => 'Manager should still see this in their own list',
    ]);
    $createResponse->assertStatus(201);
    $itemId = $createResponse->json('data.id');

    $listResponse = $this->actingAs($manager)->getJson('/api/work-items');
    $listResponse->assertOk();
    expect(collect($listResponse->json('data'))->pluck('id'))->toContain($itemId);

    $this->actingAs($manager)->getJson("/api/work-items/{$itemId}")->assertOk();

    $statsResponse = $this->actingAs($manager)->getJson('/api/work-items/stats');
    $statsResponse->assertOk()->assertJsonPath('data.total', 1);
});

it("shows a manager a report's self-assigned task in their own index listing, view, and stats", function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $department->id]);
    $manager->assignRole(Role::User->value);
    $report = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $report->assignRole(Role::User->value);

    // The report assigns the task to themself — assigned_by_id is the report's
    // own id, never the manager's, so this can only be found via the reporting
    // chain (assigned_to_id being a descendant of the manager).
    $createResponse = $this->actingAs($report)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $report->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Self-assigned by report',
        'description' => 'Manager should still see this even though they had no hand in it',
    ]);
    $createResponse->assertStatus(201);
    $itemId = $createResponse->json('data.id');

    $listResponse = $this->actingAs($manager)->getJson('/api/work-items');
    $listResponse->assertOk();
    expect(collect($listResponse->json('data'))->pluck('id'))->toContain($itemId);

    $this->actingAs($manager)->getJson("/api/work-items/{$itemId}")->assertOk();

    $statsResponse = $this->actingAs($manager)->getJson('/api/work-items/stats');
    $statsResponse->assertOk()->assertJsonPath('data.total', 1);
});

it("excludes a self-assigned task from an unrelated manager's index listing", function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $department->id]);
    $manager->assignRole(Role::User->value);
    $report = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $report->assignRole(Role::User->value);
    $unrelatedManager = User::factory()->create(['department_id' => $department->id]);
    $unrelatedManager->assignRole(Role::User->value);

    $createResponse = $this->actingAs($report)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $report->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Self-assigned by report',
        'description' => 'Should stay invisible to a manager outside the chain',
    ]);
    $createResponse->assertStatus(201);
    $itemId = $createResponse->json('data.id');

    $this->actingAs($unrelatedManager)->getJson("/api/work-items/{$itemId}")->assertStatus(403);

    $listResponse = $this->actingAs($unrelatedManager)->getJson('/api/work-items');
    $listResponse->assertOk();
    expect(collect($listResponse->json('data'))->pluck('id'))->not->toContain($itemId);
});

it('allows a superadmin to view work items across all departments', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::SuperAdmin->value);
    $userA = User::factory()->create(['department_id' => $deptA->id]);
    $userB = User::factory()->create(['department_id' => $deptB->id]);

    WorkItem::factory()->create([
        'department_id' => $deptA->id,
        'created_by_id' => $userA->id,
        'assigned_to_id' => $userA->id,
    ]);
    WorkItem::factory()->create([
        'department_id' => $deptB->id,
        'created_by_id' => $userB->id,
        'assigned_to_id' => $userB->id,
    ]);

    $response = $this->actingAs($superAdmin)->getJson('/api/work-items');

    $response->assertOk();
    expect($response->json('meta.total') ?? count($response->json('data')))->toBeGreaterThanOrEqual(2);
});
