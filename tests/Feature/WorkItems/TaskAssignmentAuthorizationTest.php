<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use App\Services\HierarchyService;
use App\Services\TaskAssignmentAuthorizer;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function createWorkItemAs(\Illuminate\Testing\TestResponse|\Tests\TestCase $test, User $actor, User $assignTo, Department $department): \Illuminate\Testing\TestResponse
{
    return $test->actingAs($actor)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $assignTo->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Assignment test',
        'description' => 'Testing task assignment authorization',
    ]);
}

it('always allows self-assignment for a plain user', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $user->assignRole(Role::User->value);

    createWorkItemAs($this, $user, $user, $department)->assertStatus(201);
});

it('allows a direct manager to assign a task to their direct report', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $department->id]);
    $manager->assignRole(Role::User->value);
    $report = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $report->assignRole(Role::User->value);

    createWorkItemAs($this, $manager, $report, $department)->assertStatus(201);
});

it('allows a manager three levels up to assign directly to a report three levels down', function () {
    $department = Department::factory()->create();

    $level1 = User::factory()->create(['department_id' => $department->id]);
    $level1->assignRole(Role::User->value);

    $level2 = User::factory()->create(['department_id' => $department->id, 'manager_id' => $level1->id]);
    $level2->assignRole(Role::User->value);

    $level3 = User::factory()->create(['department_id' => $department->id, 'manager_id' => $level2->id]);
    $level3->assignRole(Role::User->value);

    $level4 = User::factory()->create(['department_id' => $department->id, 'manager_id' => $level3->id]);
    $level4->assignRole(Role::User->value);

    // level1 is three levels above level4 — full-chain traversal, not just one level.
    createWorkItemAs($this, $level1, $level4, $department)->assertStatus(201);
});

it('prevents a peer with the same manager from assigning to another peer', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $department->id]);
    $manager->assignRole(Role::User->value);

    $peerA = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $peerA->assignRole(Role::User->value);
    $peerB = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $peerB->assignRole(Role::User->value);

    createWorkItemAs($this, $peerA, $peerB, $department)->assertStatus(403);
});

it('prevents an unrelated user in a different branch of the hierarchy from assigning, even in the same department', function () {
    $department = Department::factory()->create();

    $managerA = User::factory()->create(['department_id' => $department->id]);
    $managerA->assignRole(Role::User->value);
    $reportA = User::factory()->create(['department_id' => $department->id, 'manager_id' => $managerA->id]);
    $reportA->assignRole(Role::User->value);

    $managerB = User::factory()->create(['department_id' => $department->id]);
    $managerB->assignRole(Role::User->value);
    $reportB = User::factory()->create(['department_id' => $department->id, 'manager_id' => $managerB->id]);
    $reportB->assignRole(Role::User->value);

    createWorkItemAs($this, $reportA, $reportB, $department)->assertStatus(403);
});

it('allows an admin to assign to any user regardless of hierarchy or department', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $admin = User::factory()->create(['department_id' => $deptA->id]);
    $admin->assignRole(Role::Admin->value);
    $unrelatedUser = User::factory()->create(['department_id' => $deptB->id]);
    $unrelatedUser->assignRole(Role::User->value);

    createWorkItemAs($this, $admin, $unrelatedUser, $deptB)->assertStatus(201);
});

it('allows a superadmin to assign to any user regardless of hierarchy or department', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::SuperAdmin->value);
    $unrelatedUser = User::factory()->create(['department_id' => $deptB->id]);
    $unrelatedUser->assignRole(Role::User->value);

    createWorkItemAs($this, $superAdmin, $unrelatedUser, $deptB)->assertStatus(201);
});

it('rejects a manager_id change that would create a cycle, with a 422', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $ancestor = User::factory()->create();
    $ancestor->assignRole(Role::User->value);
    $descendant = User::factory()->create(['manager_id' => $ancestor->id]);
    $descendant->assignRole(Role::User->value);

    // Making $ancestor report to their own descendant would create a loop.
    $response = $this->actingAs($admin)->patchJson("/api/users/{$ancestor->id}/manager", [
        'manager_id' => $descendant->id,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('manager_id');
    expect($ancestor->fresh()->manager_id)->toBeNull();
});

it('rejects assigning a user as their own manager', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$user->id}/manager", [
        'manager_id' => $user->id,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('manager_id');
});

it('allows a valid manager reassignment that does not create a cycle', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $newManager = User::factory()->create();
    $newManager->assignRole(Role::User->value);
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$user->id}/manager", [
        'manager_id' => $newManager->id,
    ]);

    $response->assertOk();
    expect($user->fresh()->manager_id)->toBe($newManager->id);
});

it('returns the full-depth descendant list for a 3+ level chain via the assignable-users endpoint', function () {
    $level1 = User::factory()->create();
    $level1->assignRole(Role::User->value);
    $level2 = User::factory()->create(['manager_id' => $level1->id]);
    $level2->assignRole(Role::User->value);
    $level3 = User::factory()->create(['manager_id' => $level2->id]);
    $level3->assignRole(Role::User->value);
    $level4 = User::factory()->create(['manager_id' => $level3->id]);
    $level4->assignRole(Role::User->value);

    // A user completely outside the chain must never appear.
    $unrelated = User::factory()->create();
    $unrelated->assignRole(Role::User->value);

    $response = $this->actingAs($level1)->getJson('/api/users/me/assignable');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($level1->id) // self
        ->toContain($level2->id)
        ->toContain($level3->id)
        ->toContain($level4->id)
        ->not->toContain($unrelated->id);
});

it('HierarchyService::getDescendants returns every level of a 3+ level chain, not just the first', function () {
    $level1 = User::factory()->create();
    $level2 = User::factory()->create(['manager_id' => $level1->id]);
    $level3 = User::factory()->create(['manager_id' => $level2->id]);
    $level4 = User::factory()->create(['manager_id' => $level3->id]);

    $descendants = app(HierarchyService::class)->getDescendants($level1);
    $ids = $descendants->pluck('id');

    expect($ids)->toHaveCount(3)
        ->toContain($level2->id)
        ->toContain($level3->id)
        ->toContain($level4->id);
});

it('HierarchyService::getAncestors returns every level above, nearest-first', function () {
    $level1 = User::factory()->create();
    $level2 = User::factory()->create(['manager_id' => $level1->id]);
    $level3 = User::factory()->create(['manager_id' => $level2->id]);
    $level4 = User::factory()->create(['manager_id' => $level3->id]);

    $ancestors = app(HierarchyService::class)->getAncestors($level4);

    expect($ancestors->pluck('id')->all())->toBe([$level3->id, $level2->id, $level1->id]);
});

it('TaskAssignmentAuthorizer denies two users with no ancestor relationship', function () {
    $a = User::factory()->create();
    $a->assignRole(Role::User->value);
    $b = User::factory()->create();
    $b->assignRole(Role::User->value);

    expect(app(TaskAssignmentAuthorizer::class)->canAssign($a, $b))->toBeFalse();
});
