<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function branchMasterTestAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    return $admin;
}

it('allows an admin to update a branch', function () {
    $admin = branchMasterTestAdmin();
    $branch = Branch::factory()->create(['name' => 'Old Branch', 'code' => 'OLD']);

    $response = $this->actingAs($admin)->patchJson("/api/branches/{$branch->id}", [
        'name' => 'New Branch',
        'code' => 'NEW',
        'type' => 'client',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Branch')
        ->assertJsonPath('data.code', 'NEW')
        ->assertJsonPath('data.type', 'client');
});

it('rejects a non-admin updating a branch', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);
    $branch = Branch::factory()->create();

    $this->actingAs($user)->patchJson("/api/branches/{$branch->id}", [
        'name' => 'Hijacked',
    ])->assertStatus(403);
});

it('toggles a branch active/inactive', function () {
    $admin = branchMasterTestAdmin();
    $branch = Branch::factory()->create(['is_active' => true]);

    $this->actingAs($admin)->patchJson("/api/branches/{$branch->id}/toggle-active")
        ->assertOk()->assertJsonPath('data.is_active', false);

    $this->actingAs($admin)->patchJson("/api/branches/{$branch->id}/toggle-active")
        ->assertOk()->assertJsonPath('data.is_active', true);
});

it('excludes an inactive branch from the default index but includes it when include_inactive is passed', function () {
    $admin = branchMasterTestAdmin();
    $active = Branch::factory()->create(['is_active' => true]);
    $inactive = Branch::factory()->create(['is_active' => false]);

    $default = $this->actingAs($admin)->getJson('/api/branches');
    $ids = collect($default->json('data'))->pluck('id');
    expect($ids)->toContain($active->id)->not->toContain($inactive->id);

    $withInactive = $this->actingAs($admin)->getJson('/api/branches?include_inactive=1');
    $idsWithInactive = collect($withInactive->json('data'))->pluck('id');
    expect($idsWithInactive)->toContain($active->id)->toContain($inactive->id);
});

it('soft-deletes a branch — row persists, excluded from every listing', function () {
    $admin = branchMasterTestAdmin();
    $branch = Branch::factory()->create();

    $this->actingAs($admin)->deleteJson("/api/branches/{$branch->id}")
        ->assertOk()->assertJsonPath('success', true);

    $this->assertSoftDeleted('branches', ['id' => $branch->id]);

    $default = $this->actingAs($admin)->getJson('/api/branches');
    expect(collect($default->json('data'))->pluck('id'))->not->toContain($branch->id);

    $withInactive = $this->actingAs($admin)->getJson('/api/branches?include_inactive=1');
    expect(collect($withInactive->json('data'))->pluck('id'))->not->toContain($branch->id);
});

it('rejects a non-admin deleting a branch', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);
    $branch = Branch::factory()->create();

    $this->actingAs($user)->deleteJson("/api/branches/{$branch->id}")->assertStatus(403);

    $this->assertDatabaseHas('branches', ['id' => $branch->id, 'deleted_at' => null]);
});

it('lets a new branch reuse the code of a soft-deleted one', function () {
    $admin = branchMasterTestAdmin();
    $original = Branch::factory()->create(['code' => 'REUSE']);
    $this->actingAs($admin)->deleteJson("/api/branches/{$original->id}")->assertOk();

    $response = $this->actingAs($admin)->postJson('/api/branches', [
        'name' => 'Fresh Branch',
        'code' => 'REUSE',
        'type' => 'branch',
    ]);

    $response->assertStatus(201)->assertJsonPath('data.code', 'REUSE');
});
