<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ArtistEmailVisibilityTest extends TestCase
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

    public function test_email_is_visible_by_default_to_other_users(): void
    {
        $artist = User::factory()->create(['show_email' => true]);
        $artist->assignRole(UserRole::ARTIST);
        $artist->userBalance()->create();

        $viewer = User::factory()->create();
        $viewer->assignRole(UserRole::USER);

        $response = $this->actingAs($viewer, 'api')
            ->getJson("/api/show_artist_prifile/{$artist->id}");

        $response->assertOk();
        $response->assertJsonPath('data.user.email', $artist->email);
    }

    public function test_email_is_hidden_from_other_users_when_show_email_is_false(): void
    {
        $artist = User::factory()->create(['show_email' => false]);
        $artist->assignRole(UserRole::ARTIST);
        $artist->userBalance()->create();

        $viewer = User::factory()->create();
        $viewer->assignRole(UserRole::USER);

        $response = $this->actingAs($viewer, 'api')
            ->getJson("/api/show_artist_prifile/{$artist->id}");

        $response->assertOk();
        $response->assertJsonPath('data.user.email', null);
    }

    public function test_artist_always_sees_own_email_even_when_hidden(): void
    {
        $artist = User::factory()->create(['show_email' => false]);
        $artist->assignRole(UserRole::ARTIST);
        $artist->userBalance()->create();

        $response = $this->actingAs($artist, 'api')
            ->getJson("/api/show_artist_prifile/{$artist->id}");

        $response->assertOk();
        $response->assertJsonPath('data.user.email', $artist->email);
    }

    public function test_artist_can_toggle_email_visibility_off(): void
    {
        $artist = User::factory()->create(['show_email' => true]);
        $artist->assignRole(UserRole::ARTIST);

        $response = $this->actingAs($artist, 'api')
            ->postJson('/api/profile/toggle-email-visibility', ['show_email' => false]);

        $response->assertOk();
        $response->assertJsonPath('data.show_email', false);
        $this->assertDatabaseHas('users', ['id' => $artist->id, 'show_email' => false]);
    }

    public function test_artist_can_toggle_email_visibility_on(): void
    {
        $artist = User::factory()->create(['show_email' => false]);
        $artist->assignRole(UserRole::ARTIST);

        $response = $this->actingAs($artist, 'api')
            ->postJson('/api/profile/toggle-email-visibility', ['show_email' => true]);

        $response->assertOk();
        $response->assertJsonPath('data.show_email', true);
        $this->assertDatabaseHas('users', ['id' => $artist->id, 'show_email' => true]);
    }

    public function test_toggle_email_visibility_requires_authentication(): void
    {
        $response = $this->postJson('/api/profile/toggle-email-visibility', ['show_email' => false]);

        $response->assertUnauthorized();
    }
}
