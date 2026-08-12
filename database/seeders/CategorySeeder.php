<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Department;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $technical = Department::where('code', 'TECH')->first();

        Category::firstOrCreate(['name' => 'Hardware', 'department_id' => $technical?->id]);
        Category::firstOrCreate(['name' => 'Software', 'department_id' => $technical?->id]);
        Category::firstOrCreate(['name' => 'Network', 'department_id' => $technical?->id]);
        Category::firstOrCreate(['name' => 'General', 'department_id' => null]);
    }
}
