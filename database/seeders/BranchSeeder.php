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
        Branch::firstOrCreate(['code' => 'BR01'], ['name' => 'City Branch', 'type' => BranchType::Branch->value]);
        Branch::firstOrCreate(['code' => 'CL01'], ['name' => 'Acme Corp', 'type' => BranchType::Client->value]);
    }
}
