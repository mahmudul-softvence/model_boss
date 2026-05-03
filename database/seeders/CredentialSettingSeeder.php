<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\CredentialSettings;
use Illuminate\Database\Seeder;

class CredentialSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CredentialSettings::groups() as $group => $fields) {
            foreach ($fields as $field => $configKey) {
                $value = config($configKey);

                if ($value === null || $value === '') {
                    continue;
                }

                Setting::updateOrCreate(
                    ['key' => CredentialSettings::settingKey($group, $field)],
                    ['value' => $value]
                );
            }
        }
    }
}
