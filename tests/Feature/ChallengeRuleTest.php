<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ChallengeRuleTest extends TestCase
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

    public function test_admin_can_retrieve_default_empty_challenge_rules(): void
    {
        $admin = $this->createAdmin();

        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/settings/challenge_rules')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rules', []);
    }

    public function test_admin_can_retrieve_saved_challenge_rules(): void
    {
        $admin = $this->createAdmin();

        Setting::setChallengeRules(['Rule one', 'Rule two']);

        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/settings/challenge_rules')
            ->assertOk()
            ->assertJsonPath('data.rules.0', 'Rule one')
            ->assertJsonPath('data.rules.1', 'Rule two');
    }

    public function test_admin_can_update_challenge_rules(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeadersFor($admin))->putJson(
            '/api/admin/settings/challenge_rules',
            [
                'rules' => [
                    'Accepting the offer reserves the challenge amount from your balance.',
                    'The challenge remains active for the selected duration.',
                    'Players must follow the match rules before the challenge can continue.',
                ],
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Challenge rules updated successfully')
            ->assertJsonPath(
                'data.rules.0',
                'Accepting the offer reserves the challenge amount from your balance.',
            )
            ->assertJsonPath('data.rules.2', 'Players must follow the match rules before the challenge can continue.');

        $this->assertSame(
            [
                'Accepting the offer reserves the challenge amount from your balance.',
                'The challenge remains active for the selected duration.',
                'Players must follow the match rules before the challenge can continue.',
            ],
            Setting::getChallengeRules(),
        );
        $this->assertDatabaseHas('settings', ['key' => Setting::CHALLENGE_RULES_KEY]);
    }

    public function test_admin_update_overwrites_existing_challenge_rules(): void
    {
        $admin = $this->createAdmin();

        Setting::setChallengeRules(['Old rule']);

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/settings/challenge_rules', [
                'rules' => ['New rule'],
            ])
            ->assertOk();

        $this->assertDatabaseCount('settings', 1);
        $this->assertSame(['New rule'], Setting::getChallengeRules());
    }

    public function test_admin_update_validates_payload(): void
    {
        $admin = $this->createAdmin();

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/settings/challenge_rules', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rules']);

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/settings/challenge_rules', [
                'rules' => ['Valid rule', 123],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rules.1']);
    }

    public function test_admin_write_routes_require_authentication_and_admin_role(): void
    {
        $this->getJson('/api/admin/settings/challenge_rules')->assertUnauthorized();
        $this->putJson('/api/admin/settings/challenge_rules', [
            'rules' => ['Rule'],
        ])->assertUnauthorized();

        $user = $this->createUser();

        $this->withHeaders($this->authHeadersFor($user))
            ->getJson('/api/admin/settings/challenge_rules')
            ->assertForbidden();

        $this->withHeaders($this->authHeadersFor($user))
            ->putJson('/api/admin/settings/challenge_rules', [
                'rules' => ['Rule'],
            ])
            ->assertForbidden();
    }

    public function test_authenticated_user_can_retrieve_challenge_rules(): void
    {
        $user = $this->createUser();

        Setting::setChallengeRules(['Rule one', 'Rule two']);

        $this->withHeaders($this->authHeadersFor($user))
            ->getJson('/api/challenge-rules')
            ->assertOk()
            ->assertJsonPath('data.rules.0', 'Rule one')
            ->assertJsonPath('data.rules.1', 'Rule two');
    }

    public function test_authenticated_admin_can_retrieve_challenge_rules(): void
    {
        $admin = $this->createAdmin();

        Setting::setChallengeRules(['Rule one']);

        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/challenge-rules')
            ->assertOk()
            ->assertJsonPath('data.rules.0', 'Rule one');
    }

    public function test_guest_cannot_retrieve_challenge_rules(): void
    {
        $this->getJson('/api/challenge-rules')->assertUnauthorized();
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
