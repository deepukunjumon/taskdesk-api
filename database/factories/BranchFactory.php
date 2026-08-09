<?php

namespace Database\Factories;

use App\Enums\BranchType;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'type' => fake()->randomElement(BranchType::values()),
        ];
    }
}
