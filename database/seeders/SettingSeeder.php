<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::updateOrCreate(
            ['key' => 'auto_accept_withdrawals'],
            ['value' => 'false'],
        );

        Setting::updateOrCreate(
            ['key' => 'auto_offer_challenges'],
            ['value' => 'false'],
        );

        if (Setting::getChallengeRules() === []) {
            Setting::setChallengeRules([
                'Both players must be ready before the match can start.',
                'Use only the selected game and agreed match settings.',
                'No cheating, boosting, account sharing, or match fixing.',
                'Submit results honestly. Disputes may be reviewed by admins.',
                'Toxic behavior, harassment, or abuse may result in suspension.',
                'Withdrawal and payout rules still apply to challenge winnings.',
            ]);
        }
    }
}
