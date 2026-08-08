<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@taskdesk.test',
            'password' => 'password',
        ]);
        $superAdmin->assignRole(Role::SuperAdmin->value);

        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@taskdesk.test',
            'password' => 'password',
        ]);
        $admin->assignRole(Role::Admin->value);

        $employee = User::factory()->create([
            'name' => 'Employee User',
            'email' => 'employee@taskdesk.test',
            'password' => 'password',
        ]);
        $employee->assignRole(Role::Employee->value);
    }
}
