<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('allows a superadmin to access the role-gated users endpoint', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SuperAdmin->value);

    $response = $this->actingAs($user)->getJson('/api/users');

    $response->assertOk();
});

it('denies an employee access to the role-gated users endpoint with a 403', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Employee->value);

    $response = $this->actingAs($user)->getJson('/api/users');

    $response->assertStatus(403)
        ->assertJsonPath('success', false);
});
