<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('returns a reporting manager their direct reports', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $department->id]);
    $manager->assignRole(Role::User->value);

    $reportA = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $reportA->assignRole(Role::User->value);
    $reportB = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $reportB->assignRole(Role::User->value);

    $response = $this->actingAs($manager)->getJson('/api/users/me/reports');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toHaveCount(2)->toContain($reportA->id)->toContain($reportB->id);
});

it('returns an empty list for a user with no direct reports', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $response = $this->actingAs($user)->getJson('/api/users/me/reports');

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

it('never includes reports further down the chain — direct reports only', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::User->value);
    $directReport = User::factory()->create(['manager_id' => $manager->id]);
    $directReport->assignRole(Role::User->value);
    $indirectReport = User::factory()->create(['manager_id' => $directReport->id]);
    $indirectReport->assignRole(Role::User->value);

    $response = $this->actingAs($manager)->getJson('/api/users/me/reports');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($directReport->id)->not->toContain($indirectReport->id);
});

it('never includes the manager themself', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::User->value);
    User::factory()->create(['manager_id' => $manager->id])->assignRole(Role::User->value);

    $response = $this->actingAs($manager)->getJson('/api/users/me/reports');

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->not->toContain($manager->id);
});

it('includes department, manager, and reports_count on each report', function () {
    $department = Department::factory()->create(['name' => 'Technical']);
    $manager = User::factory()->create(['department_id' => $department->id]);
    $manager->assignRole(Role::User->value);
    $report = User::factory()->create(['department_id' => $department->id, 'manager_id' => $manager->id]);
    $report->assignRole(Role::User->value);
    User::factory()->create(['manager_id' => $report->id])->assignRole(Role::User->value);

    $response = $this->actingAs($manager)->getJson('/api/users/me/reports');

    $entry = collect($response->json('data'))->firstWhere('id', $report->id);
    expect($entry['department']['name'])->toBe('Technical');
    expect($entry['manager']['id'])->toBe($manager->id);
    expect($entry['reports_count'])->toBe(1);
});

it('does not require an admin/superadmin role — any authenticated user can see their own reports', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::User->value);
    User::factory()->create(['manager_id' => $manager->id])->assignRole(Role::User->value);

    $this->actingAs($manager)->getJson('/api/users/me/reports')->assertOk();
});

it('rejects an unauthenticated request', function () {
    $this->getJson('/api/users/me/reports')->assertStatus(401);
});
