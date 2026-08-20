<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function createUserTestAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    return $admin;
}

function createUserTestSuperAdmin(): User
{
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::SuperAdmin->value);

    return $superAdmin;
}

// ---------------------------------------------------------------------------
// Access
// ---------------------------------------------------------------------------

it('allows an admin to create a plain user', function () {
    $admin = createUserTestAdmin();

    $response = $this->actingAs($admin)->postJson('/api/users', [
        'name' => 'New Hire',
        'email' => 'newhire@taskdesk.test',
        'password' => 'password123',
        'role' => 'user',
        'employee_code' => 'EMP500',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'New Hire')
        ->assertJsonPath('data.email', 'newhire@taskdesk.test')
        ->assertJsonPath('data.roles.0', 'user')
        ->assertJsonPath('data.employee_code', 'EMP500')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('users', ['email' => 'newhire@taskdesk.test']);
});

it('allows a superadmin to create a user', function () {
    $superAdmin = createUserTestSuperAdmin();

    $response = $this->actingAs($superAdmin)->postJson('/api/users', [
        'name' => 'Another Hire',
        'email' => 'another@taskdesk.test',
        'password' => 'password123',
        'role' => 'user',
        'employee_code' => 'EMP501',
    ]);

    $response->assertStatus(201);
});

it('rejects a non-admin creating a user', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)->postJson('/api/users', [
        'name' => 'Nope',
        'email' => 'nope@taskdesk.test',
        'password' => 'password123',
        'role' => 'user',
        'employee_code' => 'EMP502',
    ])->assertStatus(403);
});

it('rejects an unauthenticated request', function () {
    $this->postJson('/api/users', [
        'name' => 'Nope',
        'email' => 'nope@taskdesk.test',
        'password' => 'password123',
        'role' => 'user',
    ])->assertStatus(401);
});

// ---------------------------------------------------------------------------
// Password can log in immediately
// ---------------------------------------------------------------------------

it('lets the newly created user log in with the given password', function () {
    $admin = createUserTestAdmin();

    $this->actingAs($admin)->postJson('/api/users', [
        'name' => 'Login Test',
        'email' => 'logintest@taskdesk.test',
        'password' => 'secret1234',
        'role' => 'user',
        'employee_code' => 'EMP503',
    ])->assertStatus(201);

    $login = $this->postJson('/api/login', [
        'email' => 'logintest@taskdesk.test',
        'password' => 'secret1234',
    ]);

    $login->assertOk()->assertJsonPath('data.user.email', 'logintest@taskdesk.test');
});

// ---------------------------------------------------------------------------
// employee_code role rule (mirrors UpdateUserRequest's rule)
// ---------------------------------------------------------------------------

it('requires employee_code when creating a plain user', function () {
    $admin = createUserTestAdmin();

    $response = $this->actingAs($admin)->postJson('/api/users', [
        'name' => 'No Code',
        'email' => 'nocode@taskdesk.test',
        'password' => 'password123',
        'role' => 'user',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('employee_code');
});

it('rejects employee_code when creating an admin', function () {
    $admin = createUserTestAdmin();

    $response = $this->actingAs($admin)->postJson('/api/users', [
        'name' => 'Admin With Code',
        'email' => 'adminwithcode@taskdesk.test',
        'password' => 'password123',
        'role' => 'admin',
        'employee_code' => 'EMP504',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('employee_code');
});

it('allows creating an admin without an employee_code', function () {
    $admin = createUserTestAdmin();

    $response = $this->actingAs($admin)->postJson('/api/users', [
        'name' => 'Clean Admin',
        'email' => 'cleanadmin@taskdesk.test',
        'password' => 'password123',
        'role' => 'admin',
    ]);

    $response->assertStatus(201)->assertJsonPath('data.roles.0', 'admin');
});

it('rejects a duplicate employee_code', function () {
    $admin = createUserTestAdmin();
    $existing = User::factory()->create(['employee_code' => 'DUPE1']);
    $existing->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->postJson('/api/users', [
        'name' => 'Duplicate',
        'email' => 'duplicate@taskdesk.test',
        'password' => 'password123',
        'role' => 'user',
        'employee_code' => 'DUPE1',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('employee_code');
});

it('rejects a duplicate email', function () {
    $admin = createUserTestAdmin();
    User::factory()->create(['email' => 'taken@taskdesk.test']);

    $response = $this->actingAs($admin)->postJson('/api/users', [
        'name' => 'Duplicate Email',
        'email' => 'taken@taskdesk.test',
        'password' => 'password123',
        'role' => 'user',
        'employee_code' => 'EMP505',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});

// ---------------------------------------------------------------------------
// Privilege escalation guard — only superadmin can mint another superadmin
// ---------------------------------------------------------------------------

it('rejects an admin creating a superadmin', function () {
    $admin = createUserTestAdmin();

    $response = $this->actingAs($admin)->postJson('/api/users', [
        'name' => 'Would-be Superadmin',
        'email' => 'wouldbe@taskdesk.test',
        'password' => 'password123',
        'role' => 'superadmin',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('role');
});

it('allows a superadmin to create another superadmin', function () {
    $superAdmin = createUserTestSuperAdmin();

    $response = $this->actingAs($superAdmin)->postJson('/api/users', [
        'name' => 'Second Superadmin',
        'email' => 'second-superadmin@taskdesk.test',
        'password' => 'password123',
        'role' => 'superadmin',
    ]);

    $response->assertStatus(201)->assertJsonPath('data.roles.0', 'superadmin');
});

// ---------------------------------------------------------------------------
// Optional department/manager assignment at creation time
// ---------------------------------------------------------------------------

it('assigns department and manager at creation time', function () {
    $admin = createUserTestAdmin();
    $department = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $department->id]);
    $manager->assignRole(Role::User->value);

    $response = $this->actingAs($admin)->postJson('/api/users', [
        'name' => 'Placed Employee',
        'email' => 'placed@taskdesk.test',
        'password' => 'password123',
        'role' => 'user',
        'employee_code' => 'EMP506',
        'department_id' => $department->id,
        'manager_id' => $manager->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.department.id', $department->id)
        ->assertJsonPath('data.manager.id', $manager->id);
});
