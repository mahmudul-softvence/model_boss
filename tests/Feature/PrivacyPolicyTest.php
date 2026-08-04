<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PrivacyPolicy;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'api');
        }
    }

    public function test_public_can_retrieve_default_privacy_policy(): void
    {
        $this->getJson('/api/privacy-policy')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Privacy Policy')
            ->assertJsonPath('data.content', '');
    }

    public function test_public_can_retrieve_saved_privacy_policy(): void
    {
        PrivacyPolicy::factory()->create([
            'title' => 'Our Privacy Policy',
            'content' => 'We protect your data.',
        ]);

        $this->getJson('/api/privacy-policy')
            ->assertOk()
            ->assertJsonPath('data.title', 'Our Privacy Policy')
            ->assertJsonPath('data.content', 'We protect your data.');
    }

    public function test_admin_can_show_privacy_policy(): void
    {
        $admin = $this->createAdmin();

        PrivacyPolicy::factory()->create([
            'title' => 'Admin Privacy Policy',
            'content' => 'Admin visible content.',
        ]);

        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/privacy-policy')
            ->assertOk()
            ->assertJsonPath('data.title', 'Admin Privacy Policy')
            ->assertJsonPath('data.content', 'Admin visible content.');
    }

    public function test_admin_can_update_privacy_policy(): void
    {
        $admin = $this->createAdmin();

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/privacy-policy', [
                'title' => 'Updated Privacy Policy',
                'content' => "Line one.\nLine two.",
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Privacy policy updated successfully')
            ->assertJsonPath('data.title', 'Updated Privacy Policy')
            ->assertJsonPath('data.content', "Line one.\nLine two.");

        $this->assertDatabaseCount('privacy_policies', 1);
        $this->assertSame(
            [
                'title' => 'Updated Privacy Policy',
                'content' => "Line one.\nLine two.",
            ],
            PrivacyPolicy::currentContent(),
        );
    }

    public function test_admin_update_overwrites_existing_privacy_policy(): void
    {
        $admin = $this->createAdmin();

        PrivacyPolicy::factory()->create([
            'title' => 'Old Title',
            'content' => 'Old content',
        ]);

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/privacy-policy', [
                'title' => 'New Title',
                'content' => 'New content',
            ])
            ->assertOk();

        $this->assertDatabaseCount('privacy_policies', 1);
        $this->assertSame('New Title', PrivacyPolicy::currentContent()['title']);
        $this->assertSame('New content', PrivacyPolicy::currentContent()['content']);
    }

    public function test_admin_update_validates_payload(): void
    {
        $admin = $this->createAdmin();

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/privacy-policy', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_admin_routes_require_authentication_and_admin_role(): void
    {
        $this->getJson('/api/admin/privacy-policy')->assertUnauthorized();
        $this->putJson('/api/admin/privacy-policy', [
            'title' => 'Privacy Policy',
            'content' => 'Content',
        ])->assertUnauthorized();

        $user = User::factory()->create();
        $user->assignRole(UserRole::USER->value);

        $this->withHeaders($this->authHeadersFor($user))
            ->getJson('/api/admin/privacy-policy')
            ->assertForbidden();

        $this->withHeaders($this->authHeadersFor($user))
            ->putJson('/api/admin/privacy-policy', [
                'title' => 'Privacy Policy',
                'content' => 'Content',
            ])
            ->assertForbidden();
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
