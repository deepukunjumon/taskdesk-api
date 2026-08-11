<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        Department::firstOrCreate(['code' => 'TECH'], ['name' => 'Technical']);
        Department::firstOrCreate(['code' => 'SOFT'], ['name' => 'Software Development']);
        Department::firstOrCreate(['code' => 'CCARE'], ['name' => 'Customer Care']);
        Department::firstOrCreate(['code' => 'HR'], ['name' => 'HR & Administration']);
        Department::firstOrCreate(['code' => 'ACC'], ['name' => 'Accounts']);
        Department::firstOrCreate(['code' => 'ADMIN'], ['name' => 'Administration']);
    }
}
