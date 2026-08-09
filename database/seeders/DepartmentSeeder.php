<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        Department::firstOrCreate(['code' => 'ITS'], ['name' => 'IT Support']);
        Department::firstOrCreate(['code' => 'FIN'], ['name' => 'Finance']);
    }
}
