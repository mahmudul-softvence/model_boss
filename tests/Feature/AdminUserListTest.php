<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminUserListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'api');
        }
    }

    public function test_admin_user_index_excludes_super_admins(): void
    {
        $admin = $this->createAdmin('admin@example.com');
        $otherAdmin = $this->createAdmin('other-admin@example.com');
        $user = User::factory()->create(['email' => 'player@example.com']);
        $user->assignRole(UserRole::USER->value);

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/users');

        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');

        $this->assertTrue($emails->contains('player@example.com'));
        $this->assertFalse($emails->contains('admin@example.com'));
        $this->assertFalse($emails->contains('other-admin@example.com'));
        $this->assertFalse($emails->contains($otherAdmin->email));
    }

    public function test_admin_user_search_excludes_super_admins(): void
    {
        $admin = $this->createAdmin('admin@example.com');
        $this->createAdmin('hidden-admin@example.com');
        $user = User::factory()->create([
            'name' => 'Visible Player',
            'email' => 'visible@example.com',
        ]);
        $user->assignRole(UserRole::USER->value);

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/users/search?keyword=admin');

        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');

        $this->assertFalse($emails->contains('admin@example.com'));
        $this->assertFalse($emails->contains('hidden-admin@example.com'));

        $visible = $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/users/search?keyword=Visible');

        $visible->assertOk()
            ->assertJsonPath('data.0.email', 'visible@example.com');
    }

    public function test_admin_user_search_ignores_super_admin_role_filter(): void
    {
        $admin = $this->createAdmin('admin@example.com');
        $user = User::factory()->create(['email' => 'normal@example.com']);
        $user->assignRole(UserRole::USER->value);

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/users/search?role=super_admin');

        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email');

        $this->assertFalse($emails->contains('admin@example.com'));
    }

    public function test_total_users_excludes_super_admins(): void
    {
        $admin = $this->createAdmin('admin@example.com');
        $this->createAdmin('second-admin@example.com');

        $user = User::factory()->create();
        $user->assignRole(UserRole::USER->value);

        $artist = User::factory()->create();
        $artist->assignRole(UserRole::ARTIST->value);

        $this->withHeaders($this->authHeadersFor($admin))
            ->getJson('/api/admin/users/count/total')
            ->assertOk()
            ->assertJsonPath('data.total_users', 2);
    }

    private function createAdmin(string $email): User
    {
        $admin = User::factory()->create(['email' => $email]);
        $admin->assignRole(UserRole::SUPER_ADMIN->value);

        return $admin;
    }

    private function authHeadersFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
