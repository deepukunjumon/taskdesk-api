<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
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
        $this->call(DepartmentSeeder::class);
        $this->call(BranchSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(SlaSettingSeeder::class);

        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@taskdesk.test',
            'password' => 'password',
        ]);
        $superAdmin->assignRole(Role::SuperAdmin->value);
    }
}
