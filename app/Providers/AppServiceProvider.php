<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\CredentialSettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('apple', AppleProvider::class);
        });

        $this->bootCredentialsFromDatabase();
    }

    private function bootCredentialsFromDatabase(): void
    {
        try {
            $configMap = CredentialSettings::configMap();

            Setting::where('key', 'like', 'credential.%')
                ->pluck('value', 'key')
                ->each(function (string $value, string $settingKey) use ($configMap): void {
                    if (isset($configMap[$settingKey])) {
                        config([$configMap[$settingKey] => $value]);
                    }
                });
        } catch (\Throwable) {
            // DB may not be available during migrations or testing setup
        }
    }
}
