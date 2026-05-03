<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Support\CredentialSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CredentialSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'api');
        }
    }

    public function test_admin_can_retrieve_all_credential_groups(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/credentials');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'mail' => ['host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name'],
                    'stripe' => ['key', 'secret', 'webhook_secret'],
                    'paypal' => ['base_url', 'client_id', 'client_secret'],
                    'moncash' => ['base_url', 'client_id', 'client_secret', 'api_key'],
                    'bitpay' => ['base_url', 'token'],
                    'twitch' => ['client_id', 'client_secret', 'webhook_secret'],
                    'facebook' => ['client_id', 'client_secret', 'redirect'],
                    'google' => ['client_id', 'client_secret', 'redirect'],
                ],
            ]);
    }

    public function test_admin_can_update_mail_credentials(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/credentials/mail', [
                'host' => 'smtp.example.com',
                'port' => '587',
                'encryption' => 'tls',
                'username' => 'user@example.com',
                'password' => 'secret',
                'from_address' => 'no-reply@example.com',
                'from_name' => 'Example App',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Mail credentials updated');

        $this->assertDatabaseHas('settings', ['key' => 'credential.mail.host', 'value' => 'smtp.example.com']);
        $this->assertDatabaseHas('settings', ['key' => 'credential.mail.port', 'value' => '587']);
        $this->assertSame('smtp.example.com', config('mail.host'));
        $this->assertSame('587', config('mail.port'));
    }

    public function test_admin_can_update_stripe_credentials(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/credentials/stripe', [
                'key' => 'pk_test_newkey',
                'secret' => 'sk_test_newsecret',
                'webhook_secret' => 'whsec_new',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('settings', ['key' => 'credential.stripe.key', 'value' => 'pk_test_newkey']);
        $this->assertDatabaseHas('settings', ['key' => 'credential.stripe.secret', 'value' => 'sk_test_newsecret']);
        $this->assertSame('pk_test_newkey', config('cashier.key'));
        $this->assertSame('sk_test_newsecret', config('cashier.secret'));
    }

    public function test_updating_credentials_overwrites_existing_values(): void
    {
        $admin = $this->createAdmin();

        Setting::create(['key' => 'credential.mail.host', 'value' => 'old.smtp.com']);

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/credentials/mail', ['host' => 'new.smtp.com']);

        $this->assertDatabaseCount('settings', 1);
        $this->assertDatabaseHas('settings', ['key' => 'credential.mail.host', 'value' => 'new.smtp.com']);
    }

    public function test_null_fields_are_skipped_when_updating(): void
    {
        $admin = $this->createAdmin();

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/credentials/mail', ['host' => null, 'port' => '465']);

        $this->assertDatabaseMissing('settings', ['key' => 'credential.mail.host']);
        $this->assertDatabaseHas('settings', ['key' => 'credential.mail.port', 'value' => '465']);
    }

    public function test_updating_unknown_group_returns_422(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/credentials/unknown_service', ['key' => 'value']);

        $response->assertStatus(422);
    }

    public function test_index_returns_db_value_over_config_fallback(): void
    {
        $admin = $this->createAdmin();

        Setting::create(['key' => 'credential.paypal.client_id', 'value' => 'db-client-id']);
        config(['services.paypal.client_id' => 'env-client-id']);

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/credentials');

        $response->assertOk()
            ->assertJsonPath('data.paypal.client_id', 'db-client-id');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/admin/credentials')->assertUnauthorized();
        $this->putJson('/api/admin/credentials/mail', [])->assertUnauthorized();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeadersFor($user))
            ->getJson('/api/admin/credentials')
            ->assertForbidden();

        $this->withHeaders($this->authHeadersFor($user))
            ->putJson('/api/admin/credentials/mail', ['host' => 'x'])
            ->assertForbidden();
    }

    public function test_credential_settings_groups_cover_all_expected_services(): void
    {
        $groups = CredentialSettings::groups();

        $this->assertArrayHasKey('mail', $groups);
        $this->assertArrayHasKey('stripe', $groups);
        $this->assertArrayHasKey('paypal', $groups);
        $this->assertArrayHasKey('moncash', $groups);
        $this->assertArrayHasKey('bitpay', $groups);
        $this->assertArrayHasKey('twitch', $groups);
        $this->assertArrayHasKey('facebook', $groups);
        $this->assertArrayHasKey('google', $groups);
    }

    public function test_config_map_has_no_duplicate_setting_keys(): void
    {
        $map = CredentialSettings::configMap();
        $keys = array_keys($map);

        $this->assertSame($keys, array_unique($keys));
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->assignRole(UserRole::SUPER_ADMIN->value);

        return $admin;
    }

    private function authHeadersFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
