<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\CredentialSettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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

        // Allow the Scramble API docs (/docs/api) to be viewed in every environment.
        Gate::define('viewApiDocs', fn ($user = null): bool => true);

        $this->bootCredentialsFromDatabase();
        $this->syncStripeSecrets();
        $this->assertProductionPaymentConfig();
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

    private function syncStripeSecrets(): void
    {
        $secret = config('cashier.secret');

        if (is_string($secret) && trim($secret) !== '') {
            config(['services.stripe.secret' => $secret]);
        }
    }

    private function assertProductionPaymentConfig(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $webhookSecret = config('cashier.webhook.secret');

        if (! is_string($webhookSecret) || trim($webhookSecret) === '') {
            Log::critical('Stripe webhook secret is not configured. Webhooks will be rejected until it is set.');
        }
    }
}
