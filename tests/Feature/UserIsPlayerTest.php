<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\VerifyEmailQueued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserIsPlayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_stores_and_returns_is_player_when_provided(): void
    {
        Notification::fake();
        $this->seedRoles();

        $payload = $this->registrationPayload([
            'email' => 'player@example.com',
            'is_player' => true,
        ]);

        $response = $this->postJson('/api/register', $payload);

        $response->assertOk()
            ->assertJsonPath('data.is_player', true);

        $this->assertIsBool($response->json('data.is_player'));
        $this->assertDatabaseHas('users', [
            'email' => 'player@example.com',
            'is_player' => 1,
        ]);

        $user = User::where('email', 'player@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmailQueued::class);
    }

    public function test_register_defaults_is_player_to_false_when_omitted(): void
    {
        Notification::fake();
        $this->seedRoles();

        $payload = $this->registrationPayload([
            'email' => 'viewer@example.com',
        ]);

        unset($payload['is_player']);

        $response = $this->postJson('/api/register', $payload);

        $response->assertOk()
            ->assertJsonPath('data.is_player', false);

        $this->assertIsBool($response->json('data.is_player'));
        $this->assertDatabaseHas('users', [
            'email' => 'viewer@example.com',
            'is_player' => 0,
        ]);
    }

    public function test_admin_can_create_a_user_with_is_player_enabled(): void
    {
        $this->seedRoles();
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeadersFor($admin))
            ->postJson('/api/admin/users', [
                'first_name' => 'New',
                'middle_name' => null,
                'last_name' => 'Player',
                'email' => 'new-player@example.com',
                'password' => 'secret123',
                'role' => UserRole::USER->value,
                'is_player' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_player', true);

        $this->assertIsBool($response->json('data.is_player'));
        $this->assertDatabaseHas('users', [
            'email' => 'new-player@example.com',
            'is_player' => 1,
        ]);
    }

    public function test_admin_update_can_change_is_player_and_omitting_it_preserves_the_value(): void
    {
        $this->seedRoles();
        $admin = $this->createAdmin();
        $user = User::factory()->create([
            'email' => 'existing-user@example.com',
            'is_player' => false,
        ]);
        $user->assignRole(UserRole::USER->value);

        $headers = $this->authHeadersFor($admin);

        $updateResponse = $this->withHeaders($headers)
            ->postJson("/api/admin/users/{$user->id}", [
                'first_name' => 'Existing',
                'middle_name' => null,
                'last_name' => 'User',
                'role' => UserRole::USER->value,
                'is_player' => true,
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.is_player', true);

        $this->assertIsBool($updateResponse->json('data.is_player'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_player' => 1,
        ]);

        $preserveResponse = $this->withHeaders($headers)
            ->postJson("/api/admin/users/{$user->id}", [
                'first_name' => 'Updated',
                'middle_name' => null,
                'last_name' => 'User',
                'role' => UserRole::USER->value,
            ]);

        $preserveResponse->assertOk()
            ->assertJsonPath('data.is_player', true);

        $this->assertIsBool($preserveResponse->json('data.is_player'));
        $this->assertTrue($user->fresh()->is_player);
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

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'User',
            'artist_name' => 'Test Artist',
            'email' => 'test@example.com',
            'city' => 'New York',
            'password' => 'secret123',
            'c_password' => 'secret123',
            'is_player' => false,
        ], $overrides);
    }
}
