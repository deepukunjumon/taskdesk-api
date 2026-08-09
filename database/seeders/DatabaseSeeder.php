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

        $itSupport = Department::where('code', 'ITS')->first();
        $finance = Department::where('code', 'FIN')->first();

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
            'department_id' => $itSupport?->id,
        ]);
        $admin->assignRole(Role::Admin->value);

        $employee = User::factory()->create([
            'name' => 'Employee User',
            'email' => 'employee@taskdesk.test',
            'password' => 'password',
            'department_id' => $itSupport?->id,
        ]);
        $employee->assignRole(Role::Employee->value);

        $financeAdmin = User::factory()->create([
            'name' => 'Finance Admin',
            'email' => 'financeadmin@taskdesk.test',
            'password' => 'password',
            'department_id' => $finance?->id,
        ]);
        $financeAdmin->assignRole(Role::Admin->value);

        $financeEmployee = User::factory()->create([
            'name' => 'Finance Employee',
            'email' => 'financeemployee@taskdesk.test',
            'password' => 'password',
            'department_id' => $finance?->id,
        ]);
        $financeEmployee->assignRole(Role::Employee->value);
    }
}
