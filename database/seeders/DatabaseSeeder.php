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
        ]);
        $admin->assignRole(Role::Admin->value);

        // A 3-level IT Support reporting chain: director -> manager -> {employee, teammate}.
        // "Manager" is not a role — it's just a `user` who has reports. This demonstrates:
        // full-chain assignment (director can assign directly to employee/teammate),
        // peer denial (employee/teammate cannot assign to each other), and department
        // categorization without it being an authorization boundary.
        $director = User::factory()->create([
            'name' => 'Dana Director',
            'email' => 'director@taskdesk.test',
            'password' => 'password',
            'department_id' => $itSupport?->id,
        ]);
        $director->assignRole(Role::User->value);

        $manager = User::factory()->create([
            'name' => 'Manny Manager',
            'email' => 'manager@taskdesk.test',
            'password' => 'password',
            'department_id' => $itSupport?->id,
            'manager_id' => $director->id,
        ]);
        $manager->assignRole(Role::User->value);

        $employee = User::factory()->create([
            'name' => 'Employee User',
            'email' => 'employee@taskdesk.test',
            'password' => 'password',
            'department_id' => $itSupport?->id,
            'manager_id' => $manager->id,
        ]);
        $employee->assignRole(Role::User->value);

        $teammate = User::factory()->create([
            'name' => 'Terry Teammate',
            'email' => 'teammate@taskdesk.test',
            'password' => 'password',
            'department_id' => $itSupport?->id,
            'manager_id' => $manager->id,
        ]);
        $teammate->assignRole(Role::User->value);

        // An unrelated Finance branch — same department pattern, no relation to the IT
        // chain above, for verifying assignment is denied across unrelated hierarchies.
        $financeManager = User::factory()->create([
            'name' => 'Finance Manager',
            'email' => 'financemanager@taskdesk.test',
            'password' => 'password',
            'department_id' => $finance?->id,
        ]);
        $financeManager->assignRole(Role::User->value);

        $financeEmployee = User::factory()->create([
            'name' => 'Finance Employee',
            'email' => 'financeemployee@taskdesk.test',
            'password' => 'password',
            'department_id' => $finance?->id,
            'manager_id' => $financeManager->id,
        ]);
        $financeEmployee->assignRole(Role::User->value);
    }
}
