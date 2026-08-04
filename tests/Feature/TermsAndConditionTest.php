<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\TermsAndCondition;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TermsAndConditionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'api');
        }
    }

    public function test_public_can_retrieve_default_terms_and_conditions(): void
    {
        $this->getJson('/api/terms-and-conditions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Terms and Conditions')
            ->assertJsonPath('data.content', '');
    }

    public function test_public_can_retrieve_saved_terms_and_conditions(): void
    {
        TermsAndCondition::factory()->create([
            'title' => 'Platform Terms',
            'content' => 'Follow the rules.',
        ]);

        $this->getJson('/api/terms-and-conditions')
            ->assertOk()
            ->assertJsonPath('data.title', 'Platform Terms')
            ->assertJsonPath('data.content', 'Follow the rules.');
    }

    public function test_admin_can_show_terms_and_conditions(): void
    {
        $admin = $this->createAdmin();

        TermsAndCondition::factory()->create([
            'title' => 'Admin Terms',
            'content' => 'Admin visible terms.',
        ]);

        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/terms-and-conditions')
            ->assertOk()
            ->assertJsonPath('data.title', 'Admin Terms')
            ->assertJsonPath('data.content', 'Admin visible terms.');
    }

    public function test_admin_can_update_terms_and_conditions(): void
    {
        $admin = $this->createAdmin();

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/terms-and-conditions', [
                'title' => 'Updated Terms',
                'content' => "Rule one.\nRule two.",
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Terms and conditions updated successfully')
            ->assertJsonPath('data.title', 'Updated Terms')
            ->assertJsonPath('data.content', "Rule one.\nRule two.");

        $this->assertDatabaseCount('terms_and_conditions', 1);
        $this->assertSame(
            [
                'title' => 'Updated Terms',
                'content' => "Rule one.\nRule two.",
            ],
            TermsAndCondition::currentContent(),
        );
    }

    public function test_admin_update_overwrites_existing_terms_and_conditions(): void
    {
        $admin = $this->createAdmin();

        TermsAndCondition::factory()->create([
            'title' => 'Old Terms',
            'content' => 'Old content',
        ]);

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/terms-and-conditions', [
                'title' => 'New Terms',
                'content' => 'New content',
            ])
            ->assertOk();

        $this->assertDatabaseCount('terms_and_conditions', 1);
        $this->assertSame('New Terms', TermsAndCondition::currentContent()['title']);
        $this->assertSame('New content', TermsAndCondition::currentContent()['content']);
    }

    public function test_admin_update_validates_payload(): void
    {
        $admin = $this->createAdmin();

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/terms-and-conditions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_admin_routes_require_authentication_and_admin_role(): void
    {
        $this->getJson('/api/admin/terms-and-conditions')->assertUnauthorized();
        $this->putJson('/api/admin/terms-and-conditions', [
            'title' => 'Terms and Conditions',
            'content' => 'Content',
        ])->assertUnauthorized();

        $user = User::factory()->create();
        $user->assignRole(UserRole::USER->value);

        $this->withHeaders($this->authHeadersFor($user))
            ->getJson('/api/admin/terms-and-conditions')
            ->assertForbidden();

        $this->withHeaders($this->authHeadersFor($user))
            ->putJson('/api/admin/terms-and-conditions', [
                'title' => 'Terms and Conditions',
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
