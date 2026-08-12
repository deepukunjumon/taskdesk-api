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

        foreach (['Hardware', 'Software', 'Network'] as $name) {
            $category = Category::firstOrCreate(['name' => $name]);
            if ($technical) {
                $category->departments()->syncWithoutDetaching([$technical->id]);
            }
        }

        Category::firstOrCreate(['name' => 'General']);
    }
}
