<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\CredentialSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialSettingController extends Controller
{
    public function index(): JsonResponse
    {
        $stored = Setting::where('key', 'like', 'credential.%')->pluck('value', 'key');

        $groups = [];

        foreach (CredentialSettings::groups() as $group => $fields) {
            foreach ($fields as $field => $configKey) {
                $settingKey = CredentialSettings::settingKey($group, $field);
                $groups[$group][$field] = $stored->get($settingKey) ?? config($configKey);
            }
        }

        return $this->sendResponse($groups, 'Credentials retrieved');
    }

    public function update(Request $request, string $group): JsonResponse
    {
        $groups = CredentialSettings::groups();

        if (! array_key_exists($group, $groups)) {
            return $this->sendError('Unknown credential group', [], 422);
        }

        $fields = $groups[$group];

        $validated = $request->validate(
            array_fill_keys(array_keys($fields), 'nullable|string')
        );

        foreach ($validated as $field => $value) {
            if ($value === null) {
                continue;
            }

            Setting::updateOrCreate(
                ['key' => CredentialSettings::settingKey($group, $field)],
                ['value' => $value]
            );

            config([$fields[$field] => $value]);
        }

        return $this->sendResponse([], ucfirst($group).' credentials updated');
    }
}
