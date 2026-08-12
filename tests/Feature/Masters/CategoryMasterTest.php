<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function categoryMasterTestAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    return $admin;
}

it('allows an admin to update a category', function () {
    $admin = categoryMasterTestAdmin();
    $department = Department::factory()->create();
    $category = Category::factory()->create(['name' => 'Old Category']);

    $response = $this->actingAs($admin)->patchJson("/api/categories/{$category->id}", [
        'name' => 'New Category',
        'department_ids' => [$department->id],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'New Category')
        ->assertJsonPath('data.department_ids', [$department->id]);
});

it('allows a category to be attached to multiple departments at once', function () {
    $admin = categoryMasterTestAdmin();
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $category = Category::factory()->create();

    $response = $this->actingAs($admin)->patchJson("/api/categories/{$category->id}", [
        'department_ids' => [$departmentA->id, $departmentB->id],
    ]);

    $response->assertOk();
    $ids = collect($response->json('data.department_ids'));
    expect($ids)->toHaveCount(2)->toContain($departmentA->id)->toContain($departmentB->id);

    $refetched = $this->actingAs($admin)->getJson("/api/categories?include_inactive=1&department_id={$departmentA->id}");
    expect(collect($refetched->json('data'))->pluck('id'))->toContain($category->id);

    $refetchedB = $this->actingAs($admin)->getJson("/api/categories?include_inactive=1&department_id={$departmentB->id}");
    expect(collect($refetchedB->json('data'))->pluck('id'))->toContain($category->id);
});

it('creates a category attached to multiple departments', function () {
    $admin = categoryMasterTestAdmin();
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();

    $response = $this->actingAs($admin)->postJson('/api/categories', [
        'name' => 'Multi-dept Category',
        'department_ids' => [$departmentA->id, $departmentB->id],
    ]);

    $response->assertCreated();
    $ids = collect($response->json('data.department_ids'));
    expect($ids)->toHaveCount(2)->toContain($departmentA->id)->toContain($departmentB->id);
});

it('rejects a non-admin updating a category', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);
    $category = Category::factory()->create();

    $this->actingAs($user)->patchJson("/api/categories/{$category->id}", [
        'name' => 'Hijacked',
    ])->assertStatus(403);
});

it('toggles a category active/inactive', function () {
    $admin = categoryMasterTestAdmin();
    $category = Category::factory()->create(['is_active' => true]);

    $this->actingAs($admin)->patchJson("/api/categories/{$category->id}/toggle-active")
        ->assertOk()->assertJsonPath('data.is_active', false);

    $this->actingAs($admin)->patchJson("/api/categories/{$category->id}/toggle-active")
        ->assertOk()->assertJsonPath('data.is_active', true);
});

it('excludes an inactive category from the default index but includes it when include_inactive is passed', function () {
    $admin = categoryMasterTestAdmin();
    $active = Category::factory()->create(['is_active' => true]);
    $inactive = Category::factory()->create(['is_active' => false]);

    $default = $this->actingAs($admin)->getJson('/api/categories');
    $ids = collect($default->json('data'))->pluck('id');
    expect($ids)->toContain($active->id)->not->toContain($inactive->id);

    $withInactive = $this->actingAs($admin)->getJson('/api/categories?include_inactive=1');
    $idsWithInactive = collect($withInactive->json('data'))->pluck('id');
    expect($idsWithInactive)->toContain($active->id)->toContain($inactive->id);
});

it('soft-deletes a category — row persists, excluded from every listing', function () {
    $admin = categoryMasterTestAdmin();
    $category = Category::factory()->create();

    $this->actingAs($admin)->deleteJson("/api/categories/{$category->id}")
        ->assertOk()->assertJsonPath('success', true);

    $this->assertSoftDeleted('categories', ['id' => $category->id]);

    $default = $this->actingAs($admin)->getJson('/api/categories');
    expect(collect($default->json('data'))->pluck('id'))->not->toContain($category->id);

    $withInactive = $this->actingAs($admin)->getJson('/api/categories?include_inactive=1');
    expect(collect($withInactive->json('data'))->pluck('id'))->not->toContain($category->id);
});

it('rejects a non-admin deleting a category', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);
    $category = Category::factory()->create();

    $this->actingAs($user)->deleteJson("/api/categories/{$category->id}")->assertStatus(403);

    $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
});

it('includes department-less categories when filtering by a specific department', function () {
    $admin = categoryMasterTestAdmin();
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();

    $general = Category::factory()->create(['name' => 'General']);
    $scopedToA = Category::factory()->forDepartments($departmentA)->create(['name' => 'Hardware']);
    $scopedToB = Category::factory()->forDepartments($departmentB)->create(['name' => 'Software']);

    $response = $this->actingAs($admin)->getJson("/api/categories?department_id={$departmentA->id}");
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($general->id)
        ->toContain($scopedToA->id)
        ->not->toContain($scopedToB->id);
});
