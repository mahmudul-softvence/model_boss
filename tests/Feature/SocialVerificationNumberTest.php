<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SocialVerificationNumberTest extends TestCase
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

    public function test_social_verification_number_is_returned_when_verified(): void
    {
        $artist = User::factory()->create([
            'social_verification_status' => true,
            'social_verification_number' => 'SV-12345',
        ]);
        $artist->assignRole(UserRole::ARTIST);
        $artist->userBalance()->create();

        $response = $this->actingAs($artist, 'api')
            ->getJson("/api/show_artist_prifile/{$artist->id}");

        $response->assertOk();
        $response->assertJsonPath('data.user.social_verification_status', true);
        $response->assertJsonPath('data.user.social_verification_number', 'SV-12345');
    }

    public function test_social_verification_number_is_hidden_when_not_verified(): void
    {
        $artist = User::factory()->create([
            'social_verification_status' => false,
            'social_verification_number' => 'SV-12345',
        ]);
        $artist->assignRole(UserRole::ARTIST);
        $artist->userBalance()->create();

        $response = $this->actingAs($artist, 'api')
            ->getJson("/api/show_artist_prifile/{$artist->id}");

        $response->assertOk();
        $response->assertJsonPath('data.user.social_verification_status', false);
        $response->assertJsonPath('data.user.social_verification_number', null);
    }

    public function test_registration_accepts_social_verification_number(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'artist_name' => 'JD',
            'email' => 'jane@example.com',
            'city' => 'Miami',
            'password' => 'secret123',
            'c_password' => 'secret123',
            'social_verification_status' => true,
            'social_verification_number' => 'SV-99999',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'social_verification_number' => 'SV-99999',
        ]);
    }
}
