<?php

namespace Database\Seeders;

use App\Enums\BranchType;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office', 'type' => BranchType::Branch->value]);
    }
}
