<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('reports is_reporting_manager as true for a user with at least one direct report', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::User->value);
    $report = User::factory()->create(['manager_id' => $manager->id]);
    $report->assignRole(Role::User->value);

    $response = $this->actingAs($manager)->getJson('/api/me');

    $response->assertOk()->assertJsonPath('data.abilities.is_reporting_manager', true);
});

it('reports is_reporting_manager as false for a user with no direct reports', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $response = $this->actingAs($user)->getJson('/api/me');

    $response->assertOk()->assertJsonPath('data.abilities.is_reporting_manager', false);
});

it('does not include abilities on other users in a list', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $other = User::factory()->create();
    $other->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->getJson('/api/users');

    $response->assertOk();
    $otherEntry = collect($response->json('data'))->firstWhere('id', $other->id);
    expect($otherEntry)->not->toBeNull();
    expect(array_key_exists('abilities', $otherEntry))->toBeFalse();
});
