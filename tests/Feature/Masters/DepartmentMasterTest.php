<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function departmentMasterTestAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    return $admin;
}

it('allows an admin to update a department', function () {
    $admin = departmentMasterTestAdmin();
    $department = Department::factory()->create(['name' => 'Old Name', 'code' => 'OLD']);

    $response = $this->actingAs($admin)->patchJson("/api/departments/{$department->id}", [
        'name' => 'New Name',
        'code' => 'NEW',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.code', 'NEW');
});

it('rejects a non-admin updating a department', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);
    $department = Department::factory()->create();

    $this->actingAs($user)->patchJson("/api/departments/{$department->id}", [
        'name' => 'Hijacked',
    ])->assertStatus(403);
});

it('toggles a department active/inactive', function () {
    $admin = departmentMasterTestAdmin();
    $department = Department::factory()->create(['is_active' => true]);

    $response = $this->actingAs($admin)->patchJson("/api/departments/{$department->id}/toggle-active");
    $response->assertOk()->assertJsonPath('data.is_active', false);

    $response = $this->actingAs($admin)->patchJson("/api/departments/{$department->id}/toggle-active");
    $response->assertOk()->assertJsonPath('data.is_active', true);
});

it('excludes an inactive department from the default index but includes it when include_inactive is passed', function () {
    $admin = departmentMasterTestAdmin();
    $active = Department::factory()->create(['is_active' => true]);
    $inactive = Department::factory()->create(['is_active' => false]);

    $default = $this->actingAs($admin)->getJson('/api/departments');
    $default->assertOk();
    $ids = collect($default->json('data'))->pluck('id');
    expect($ids)->toContain($active->id)->not->toContain($inactive->id);

    $withInactive = $this->actingAs($admin)->getJson('/api/departments?include_inactive=1');
    $withInactive->assertOk();
    $idsWithInactive = collect($withInactive->json('data'))->pluck('id');
    expect($idsWithInactive)->toContain($active->id)->toContain($inactive->id);
});

it('soft-deletes a department — row persists, excluded from every listing', function () {
    $admin = departmentMasterTestAdmin();
    $department = Department::factory()->create();

    $response = $this->actingAs($admin)->deleteJson("/api/departments/{$department->id}");
    $response->assertOk()->assertJsonPath('success', true);

    $this->assertSoftDeleted('departments', ['id' => $department->id]);

    $default = $this->actingAs($admin)->getJson('/api/departments');
    expect(collect($default->json('data'))->pluck('id'))->not->toContain($department->id);

    $withInactive = $this->actingAs($admin)->getJson('/api/departments?include_inactive=1');
    expect(collect($withInactive->json('data'))->pluck('id'))->not->toContain($department->id);
});

it('rejects a non-admin deleting a department', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);
    $department = Department::factory()->create();

    $this->actingAs($user)->deleteJson("/api/departments/{$department->id}")->assertStatus(403);

    $this->assertDatabaseHas('departments', ['id' => $department->id, 'deleted_at' => null]);
});

it('lets a new department reuse the code of a soft-deleted one', function () {
    $admin = departmentMasterTestAdmin();
    $original = Department::factory()->create(['code' => 'REUSE']);
    $this->actingAs($admin)->deleteJson("/api/departments/{$original->id}")->assertOk();

    $response = $this->actingAs($admin)->postJson('/api/departments', [
        'name' => 'Fresh Department',
        'code' => 'REUSE',
    ]);

    $response->assertStatus(201)->assertJsonPath('data.code', 'REUSE');
});

it('returns only id and name from the minimal options endpoint, active departments only', function () {
    $user = User::factory()->create();
    $active = Department::factory()->create(['name' => 'Technical', 'is_active' => true]);
    $inactive = Department::factory()->create(['name' => 'Retired Dept', 'is_active' => false]);

    $response = $this->actingAs($user)->getJson('/api/departments/options');

    $response->assertOk();
    $data = collect($response->json('data'));

    expect($data->pluck('id'))->toContain($active->id)->not->toContain($inactive->id);
    expect(array_keys($data->first()))->toBe(['id', 'name']);
});

it('filters the options endpoint by a name search', function () {
    $user = User::factory()->create();
    $technical = Department::factory()->create(['name' => 'Technical', 'is_active' => true]);
    $software = Department::factory()->create(['name' => 'Software Development', 'is_active' => true]);

    $response = $this->actingAs($user)->getJson('/api/departments/options?q=Tech');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($technical->id)->not->toContain($software->id);
});
