<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminUserChallengerFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_list_includes_is_challenger_flag(): void
    {
        $this->seedRoles();
        $admin = $this->createAdmin();
        // Challenge access is on by default; admins can disable it per user.
        $challenger = User::factory()->create(['email' => 'challenger@example.com', 'is_challenger' => true]);
        $regularUser = User::factory()->create(['email' => 'regular@example.com', 'is_challenger' => false]);

        $challenger->assignRole(UserRole::USER->value);
        $regularUser->assignRole(UserRole::USER->value);

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/users?limit=20');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $challenger->id,
                'is_challenger' => true,
            ])
            ->assertJsonFragment([
                'id' => $regularUser->id,
                'is_challenger' => false,
            ]);
    }

    public function test_admin_user_detail_includes_is_challenger_flag(): void
    {
        $this->seedRoles();
        $admin = $this->createAdmin();
        $challenger = User::factory()->create(['email' => 'detail-challenger@example.com', 'is_challenger' => true]);

        $challenger->assignRole(UserRole::USER->value);

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->getJson("/api/admin/users/{$challenger->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $challenger->id)
            ->assertJsonPath('data.is_challenger', true);
    }

    public function test_admin_can_revoke_and_grant_a_users_challenge_access(): void
    {
        $this->seedRoles();
        $admin = $this->createAdmin();
        $user = User::factory()->create(['email' => 'access@example.com', 'is_challenger' => true]);
        $user->assignRole(UserRole::USER->value);

        // DELETE revokes access.
        $this->withHeaders($this->authHeadersFor($admin))
            ->deleteJson("/api/admin/users/{$user->id}/challenge-access")
            ->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Challenge creation access revoked.',
            ]);

        $this->assertFalse($user->fresh()->is_challenger);

        // POST grants it back.
        $this->withHeaders($this->authHeadersFor($admin))
            ->postJson("/api/admin/users/{$user->id}/challenge-access")
            ->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Challenge creation access granted.',
            ]);

        $this->assertTrue($user->fresh()->is_challenger);
    }

    private function seedRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'api');
        }
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);

        $admin->assignRole(UserRole::SUPER_ADMIN->value);

        return $admin;
    }

    private function authHeadersFor(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.JWTAuth::fromUser($user),
        ];
    }
}
