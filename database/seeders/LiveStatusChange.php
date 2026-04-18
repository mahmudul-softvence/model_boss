<?php

namespace Database\Seeders;

use App\Enums\LiveStatus;
use App\Enums\ViewMode;
use App\Models\CheckLiveStatus;
use Illuminate\Database\Seeder;

class LiveStatusChange extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CheckLiveStatus::updateOrCreate(
            ['platform_name' => 'twitch'],
            [
                'platform_live_status' => LiveStatus::STOP->value,
                'mode' => ViewMode::LANDSCAPE->value,
                'live_started_at' => null,
                'live_stopped_at' => now(),
            ]
        );
    }
}
