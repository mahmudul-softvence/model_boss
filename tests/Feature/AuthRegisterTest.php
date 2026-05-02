<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthRegisterTest extends TestCase
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

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'John',
            'middle_name' => null,
            'last_name' => 'Doe',
            'artist_name' => 'JD Artist',
            'email' => 'john@example.com',
            'city' => 'New York',
            'password' => 'secret123',
            'c_password' => 'secret123',
        ], $overrides);
    }

    public function test_registration_succeeds_with_artist_name_and_city(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', $this->registrationPayload());

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'artist_name' => 'JD Artist',
            'city' => 'New York',
        ]);
    }

    public function test_registration_fails_without_artist_name(): void
    {
        $response = $this->postJson('/api/register', $this->registrationPayload(['artist_name' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['artist_name']);
    }

    public function test_registration_fails_without_city(): void
    {
        $response = $this->postJson('/api/register', $this->registrationPayload(['city' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['city']);
    }
}
