<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\CredentialSettings;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    public function test_public_clear_route_is_removed(): void
    {
        $this->get('/clear')->assertNotFound();
    }

    public function test_setting_seeder_disables_auto_accept_withdrawals(): void
    {
        $this->seed(SettingSeeder::class);

        $this->assertDatabaseHas('settings', [
            'key' => 'auto_accept_withdrawals',
            'value' => 'false',
        ]);
    }

    public function test_role_seeder_does_not_create_admin_without_credentials(): void
    {
        config([
            'app.admin_email' => null,
            'app.admin_password' => null,
        ]);

        $this->seed(RoleSeeder::class);

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@gmail.com',
        ]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_role_seeder_creates_admin_when_credentials_configured(): void
    {
        config([
            'app.admin_email' => 'live-admin@example.com',
            'app.admin_password' => 'strong-password-123',
            'app.admin_name' => 'Live Admin',
        ]);

        $this->seed(RoleSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'live-admin@example.com',
            'name' => 'Live Admin',
        ]);

        $user = User::where('email', 'live-admin@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('strong-password-123', $user->password));
        $this->assertTrue($user->hasRole('super_admin'));
    }

    public function test_moncash_credential_group_includes_gateway_base(): void
    {
        $groups = CredentialSettings::groups();

        $this->assertArrayHasKey('gateway_base', $groups['moncash']);
        $this->assertSame(
            'services.moncash.gateway_base',
            $groups['moncash']['gateway_base']
        );
    }

    public function test_stripe_secret_syncs_to_services_config(): void
    {
        config(['cashier.secret' => 'sk_live_synced_secret']);

        $provider = app()->getProvider(AppServiceProvider::class);
        $method = new \ReflectionMethod($provider, 'syncStripeSecrets');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertSame('sk_live_synced_secret', config('services.stripe.secret'));
    }
}
