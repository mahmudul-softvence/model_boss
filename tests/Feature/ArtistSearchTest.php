<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ArtistSearchTest extends TestCase
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

    public function test_search_by_name_returns_matching_artists(): void
    {
        $artist = User::factory()->create(['first_name' => 'Kanye', 'last_name' => 'West', 'artist_name' => 'Yeezy']);
        $artist->assignRole(UserRole::ARTIST);

        $response = $this->getJson('/api/search_artist?search=Kanye');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $artist->id]);
    }

    public function test_search_by_artist_name_returns_matching_artists(): void
    {
        $artist = User::factory()->create(['first_name' => 'John', 'last_name' => 'Smith', 'artist_name' => 'DrummerJohn']);
        $artist->assignRole(UserRole::ARTIST);

        $response = $this->getJson('/api/search_artist?search=DrummerJohn');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $artist->id]);
    }

    public function test_search_does_not_return_non_artists(): void
    {
        $user = User::factory()->create(['first_name' => 'Regular', 'last_name' => 'User', 'artist_name' => 'RegularArtist']);
        $user->assignRole(UserRole::USER);

        $response = $this->getJson('/api/search_artist?search=RegularArtist');

        $response->assertOk();
        $response->assertJsonMissing(['id' => $user->id]);
    }

    public function test_empty_search_returns_empty_results(): void
    {
        $response = $this->getJson('/api/search_artist?search=');

        $response->assertOk();
        $response->assertJsonPath('data', []);
    }
}
