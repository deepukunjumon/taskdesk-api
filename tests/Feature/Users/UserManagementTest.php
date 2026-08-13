<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function userManagementTestAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    return $admin;
}

// ---------------------------------------------------------------------------
// employee_code role rule
// ---------------------------------------------------------------------------

it('requires employee_code when editing a plain user', function () {
    $admin = userManagementTestAdmin();
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$user->id}", [
        'name' => 'Updated Name',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('employee_code');
});

it('accepts employee_code when editing a plain user', function () {
    $admin = userManagementTestAdmin();
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$user->id}", [
        'employee_code' => 'EMP001',
    ]);

    $response->assertOk()->assertJsonPath('data.employee_code', 'EMP001');
});

it('rejects employee_code for an admin', function () {
    $admin = userManagementTestAdmin();
    $target = User::factory()->create();
    $target->assignRole(Role::Admin->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$target->id}", [
        'employee_code' => 'EMP002',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('employee_code');
});

it('rejects employee_code for a superadmin', function () {
    $admin = userManagementTestAdmin();
    $target = User::factory()->create();
    $target->assignRole(Role::SuperAdmin->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$target->id}", [
        'employee_code' => 'EMP003',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('employee_code');
});

it('allows editing an admin without touching employee_code', function () {
    $admin = userManagementTestAdmin();
    $target = User::factory()->create();
    $target->assignRole(Role::Admin->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$target->id}", [
        'name' => 'Renamed Admin',
    ]);

    $response->assertOk()->assertJsonPath('data.name', 'Renamed Admin');
});

it('rejects a duplicate employee_code', function () {
    $admin = userManagementTestAdmin();
    $existing = User::factory()->create(['employee_code' => 'DUPE']);
    $existing->assignRole(Role::User->value);
    $target = User::factory()->create(['employee_code' => 'ORIG']);
    $target->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$target->id}", [
        'employee_code' => 'DUPE',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('employee_code');
});

it('rejects a non-admin editing a user', function () {
    $actor = User::factory()->create();
    $actor->assignRole(Role::User->value);
    $target = User::factory()->create(['employee_code' => 'EMP009']);
    $target->assignRole(Role::User->value);

    $this->actingAs($actor)->patchJson("/api/users/{$target->id}", [
        'name' => 'Hijacked',
    ])->assertStatus(403);
});

// ---------------------------------------------------------------------------
// Disable / relieve exclusions
// ---------------------------------------------------------------------------

it('disables a user, blocking login', function () {
    $admin = userManagementTestAdmin();
    $user = User::factory()->create(['email' => 'disableme@taskdesk.test', 'password' => 'password']);
    $user->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$user->id}/status", [
        'is_active' => false,
    ]);
    $response->assertOk()->assertJsonPath('data.is_active', false);

    $login = $this->postJson('/api/login', [
        'email' => 'disableme@taskdesk.test',
        'password' => 'password',
    ]);
    $login->assertStatus(422)->assertJsonPath('success', false);
});

it('re-enables a disabled user', function () {
    $admin = userManagementTestAdmin();
    $user = User::factory()->create(['is_active' => false]);
    $user->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$user->id}/status", [
        'is_active' => true,
    ]);

    $response->assertOk()->assertJsonPath('data.is_active', true);
});

it('marks a user relieved, setting relieved_on and is_active together', function () {
    $admin = userManagementTestAdmin();
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$user->id}/relieve", [
        'relieved_on' => '2026-08-01',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.relieved_on', '2026-08-01T00:00:00.000000Z');
});

it('excludes a disabled user from the assignable-users endpoint', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::User->value);
    $disabledReport = User::factory()->create(['manager_id' => $manager->id, 'is_active' => false]);
    $disabledReport->assignRole(Role::User->value);
    $activeReport = User::factory()->create(['manager_id' => $manager->id]);
    $activeReport->assignRole(Role::User->value);

    $response = $this->actingAs($manager)->getJson('/api/users/me/assignable');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($activeReport->id)->not->toContain($disabledReport->id);
});

it('rejects assigning a task to a relieved user even for an admin', function () {
    $admin = userManagementTestAdmin();
    $department = Department::factory()->create();
    $relieved = User::factory()->create(['department_id' => $department->id, 'is_active' => false, 'relieved_on' => '2026-08-01']);
    $relieved->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->postJson('/api/work-items', [
        'department_id' => $department->id,
        'entry_type' => 'task',
        'assigned_to_id' => $relieved->id,
        'source' => 'internal',
        'priority' => 'low',
        'subject' => 'Should be rejected',
        'description' => 'Target is relieved',
    ]);

    $response->assertStatus(403);
});

it('rejects a non-admin disabling a user', function () {
    $actor = User::factory()->create();
    $actor->assignRole(Role::User->value);
    $target = User::factory()->create();
    $target->assignRole(Role::User->value);

    $this->actingAs($actor)->patchJson("/api/users/{$target->id}/status", [
        'is_active' => false,
    ])->assertStatus(403);
});

// ---------------------------------------------------------------------------
// Subordinate count on relieve — flagged, never auto-reassigned
// ---------------------------------------------------------------------------

it('returns the subordinate count when relieving a user with direct reports, without touching their manager_id', function () {
    $admin = userManagementTestAdmin();
    $manager = User::factory()->create();
    $manager->assignRole(Role::User->value);
    $reportA = User::factory()->create(['manager_id' => $manager->id]);
    $reportA->assignRole(Role::User->value);
    $reportB = User::factory()->create(['manager_id' => $manager->id]);
    $reportB->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$manager->id}/relieve", [
        'relieved_on' => '2026-08-01',
    ]);

    $response->assertOk()->assertJsonPath('data.reports_count', 2);

    expect($reportA->fresh()->manager_id)->toBe($manager->id);
    expect($reportB->fresh()->manager_id)->toBe($manager->id);
});

it('returns a zero subordinate count when relieving a user with no direct reports', function () {
    $admin = userManagementTestAdmin();
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$user->id}/relieve", [
        'relieved_on' => '2026-08-01',
    ]);

    $response->assertOk()->assertJsonPath('data.reports_count', 0);
});

it('returns the subordinate count when disabling a user with direct reports', function () {
    $admin = userManagementTestAdmin();
    $manager = User::factory()->create();
    $manager->assignRole(Role::User->value);
    User::factory()->create(['manager_id' => $manager->id])->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->patchJson("/api/users/{$manager->id}/status", [
        'is_active' => false,
    ]);

    $response->assertOk()->assertJsonPath('data.reports_count', 1);
});

// ---------------------------------------------------------------------------
// Filtering
// ---------------------------------------------------------------------------

it('filters the admin users list by role, department, is_active and search', function () {
    $admin = userManagementTestAdmin();
    $technical = Department::factory()->create();
    $software = Department::factory()->create();

    $matching = User::factory()->create([
        'name' => 'Ajith Balachandran',
        'employee_code' => 'EMP100',
        'department_id' => $technical->id,
        'is_active' => true,
    ]);
    $matching->assignRole(Role::User->value);

    $wrongDept = User::factory()->create(['department_id' => $software->id]);
    $wrongDept->assignRole(Role::User->value);

    $inactive = User::factory()->create(['department_id' => $technical->id, 'is_active' => false]);
    $inactive->assignRole(Role::User->value);

    $wrongRole = User::factory()->create(['department_id' => $technical->id]);
    $wrongRole->assignRole(Role::Admin->value);

    $response = $this->actingAs($admin)->getJson(
        "/api/users?role=user&department_id={$technical->id}&is_active=1&q=Ajith"
    );

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($matching->id)
        ->not->toContain($wrongDept->id)
        ->not->toContain($inactive->id)
        ->not->toContain($wrongRole->id);
});

it('paginates the admin users list', function () {
    $admin = userManagementTestAdmin();
    User::factory()->count(20)->create()->each(fn (User $u) => $u->assignRole(Role::User->value));

    $response = $this->actingAs($admin)->getJson('/api/users?per_page=5');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('meta.per_page'))->toBe(5);
});
