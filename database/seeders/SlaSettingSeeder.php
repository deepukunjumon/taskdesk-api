<?php

namespace Database\Seeders;

use App\Enums\Priority;
use App\Models\SlaSetting;
use Illuminate\Database\Seeder;

class SlaSettingSeeder extends Seeder
{
    /**
     * @var array<string, int>
     */
    private const DEFAULT_HOURS = [
        'critical' => 4,
        'high' => 24,
        'medium' => 72,
        'low' => 120,
    ];

    public function run(): void
    {
        foreach (Priority::values() as $priority) {
            SlaSetting::firstOrCreate(
                ['priority' => $priority],
                ['hours' => self::DEFAULT_HOURS[$priority]],
            );
        }
    }
}
