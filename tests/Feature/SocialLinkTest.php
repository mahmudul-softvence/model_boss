<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Support\SocialLinks;
use Database\Seeders\SocialLinkSeeder;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SocialLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'api');
        }
    }

    public function test_seeder_creates_fixed_empty_social_links(): void
    {
        $this->seed(SocialLinkSeeder::class);

        $this->assertDatabaseHas('settings', ['key' => Setting::SOCIAL_LINKS_KEY]);
        $this->assertSame(SocialLinks::defaults(), Setting::getSocialLinks());
    }

    public function test_admin_can_retrieve_social_links(): void
    {
        $admin = $this->createAdmin();
        $this->seed(SocialLinkSeeder::class);

        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/settings/social_links')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.links.instagram', '')
            ->assertJsonPath('data.links.youtube', '')
            ->assertJsonStructure([
                'data' => [
                    'links' => SocialLinks::platforms(),
                ],
            ]);
    }

    public function test_admin_can_update_social_links(): void
    {
        $admin = $this->createAdmin();
        $this->seed(SocialLinkSeeder::class);

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/settings/social_links', [
                'instagram' => 'https://instagram.com/modelboss',
                'youtube' => 'https://youtube.com/@modelboss',
                'whatsapp' => 'https://wa.me/15551234567',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Social links updated successfully')
            ->assertJsonPath('data.links.instagram', 'https://instagram.com/modelboss')
            ->assertJsonPath('data.links.youtube', 'https://youtube.com/@modelboss')
            ->assertJsonPath('data.links.facebook', '');

        $links = Setting::getSocialLinks();
        $this->assertSame('https://instagram.com/modelboss', $links['instagram']);
        $this->assertSame('https://youtube.com/@modelboss', $links['youtube']);
        $this->assertSame('', $links['tiktok']);
    }

    public function test_admin_update_merges_partial_payload(): void
    {
        $admin = $this->createAdmin();

        Setting::setSocialLinks([
            'instagram' => 'https://instagram.com/old',
            'facebook' => 'https://facebook.com/old',
        ]);

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/settings/social_links', [
                'instagram' => 'https://instagram.com/new',
            ])
            ->assertOk()
            ->assertJsonPath('data.links.instagram', 'https://instagram.com/new')
            ->assertJsonPath('data.links.facebook', 'https://facebook.com/old');
    }

    public function test_admin_can_clear_a_social_link(): void
    {
        $admin = $this->createAdmin();

        Setting::setSocialLinks([
            'instagram' => 'https://instagram.com/keep',
            'tiktok' => 'https://tiktok.com/@remove',
        ]);

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/settings/social_links', [
                'tiktok' => '',
            ])
            ->assertOk()
            ->assertJsonPath('data.links.tiktok', '')
            ->assertJsonPath('data.links.instagram', 'https://instagram.com/keep');
    }

    public function test_admin_update_validates_payload(): void
    {
        $admin = $this->createAdmin();

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/settings/social_links', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['links']);

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/settings/social_links', [
                'instagram' => 'not-a-url',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['instagram']);

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/settings/social_links', [
                'myspace' => 'https://myspace.com/nope',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['links']);
    }

    public function test_admin_routes_require_authentication_and_admin_role(): void
    {
        $this->getJson('/api/admin/settings/social_links')->assertUnauthorized();
        $this->putJson('/api/admin/settings/social_links', [
            'youtube' => 'https://youtube.com/@x',
        ])->assertUnauthorized();

        $user = $this->createUser();

        $this->withHeaders($this->authHeadersFor($user))
            ->getJson('/api/admin/settings/social_links')
            ->assertForbidden();

        $this->withHeaders($this->authHeadersFor($user))
            ->putJson('/api/admin/settings/social_links', [
                'youtube' => 'https://youtube.com/@x',
            ])
            ->assertForbidden();
    }

    public function test_public_can_retrieve_social_links(): void
    {
        Setting::setSocialLinks([
            'telegram' => 'https://t.me/modelboss',
            'youtube' => 'https://youtube.com/@modelboss',
        ]);

        $this->getJson('/api/social-links')
            ->assertOk()
            ->assertJsonPath('data.links.telegram', 'https://t.me/modelboss')
            ->assertJsonPath('data.links.youtube', 'https://youtube.com/@modelboss')
            ->assertJsonStructure([
                'data' => [
                    'links' => SocialLinks::platforms(),
                ],
            ]);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->assignRole(UserRole::SUPER_ADMIN->value);

        return $admin;
    }

    private function createUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::USER->value);

        return $user;
    }

    private function authHeadersFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
