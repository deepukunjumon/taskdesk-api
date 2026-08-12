<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'is_active' => true,
        ];
    }

    /**
     * Attaches the category to the given department(s) after creation — a
     * category with none attached is "common" and applies everywhere, so
     * tests opt into a specific scope explicitly via this state.
     */
    public function forDepartments(Department|string ...$departments): static
    {
        return $this->afterCreating(function (Category $category) use ($departments) {
            $ids = collect($departments)->map(fn ($department) => $department instanceof Department ? $department->id : $department);
            $category->departments()->sync($ids);
        });
    }
}
