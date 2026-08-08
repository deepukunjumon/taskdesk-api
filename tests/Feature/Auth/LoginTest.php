<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('logs in a user with valid credentials and returns a token', function () {
    $user = User::factory()->create([
        'email' => 'superadmin@taskdesk.test',
        'password' => 'password',
    ]);
    $user->assignRole(Role::SuperAdmin->value);

    $response = $this->postJson('/api/login', [
        'email' => 'superadmin@taskdesk.test',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'superadmin@taskdesk.test')
        ->assertJsonStructure(['data' => ['user', 'token']]);
});

it('rejects login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'superadmin@taskdesk.test',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'superadmin@taskdesk.test',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});
