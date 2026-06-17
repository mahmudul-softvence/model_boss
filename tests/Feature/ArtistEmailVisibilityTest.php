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

    public function test_name_and_private_totals_are_hidden_from_other_users_when_visibility_is_off(): void
    {
        $artist = User::factory()->create([
            'first_name' => 'Hidden',
            'middle_name' => 'Private',
            'last_name' => 'Artist',
            'artist_name' => 'Secret Stage',
            'show_name' => false,
            'show_total_earning' => false,
            'show_total_referral_earning' => false,
            'show_total_tip_received' => false,
            'show_total_withdraw' => false,
            'show_total_balance' => false,
            'show_total_bet' => false,
        ]);
        $artist->assignRole(UserRole::ARTIST);
        $artist->userBalance()->create([
            'total_earning' => 125.50,
            'total_referral_earning' => 25.25,
            'total_tip_received' => 10.75,
            'total_withdraw' => 40.00,
            'total_balance' => 60.00,
            'total_bet' => 15.00,
        ]);

        $viewer = User::factory()->create();
        $viewer->assignRole(UserRole::USER);

        $response = $this->actingAs($viewer, 'api')
            ->getJson("/api/show_artist_prifile/{$artist->id}");

        $response->assertOk();
        $response->assertJsonPath('data.user.name', null);
        $response->assertJsonPath('data.user.first_name', null);
        $response->assertJsonPath('data.user.middle_name', null);
        $response->assertJsonPath('data.user.last_name', null);
        $response->assertJsonPath('data.user.artist_name', 'Secret Stage');
        $response->assertJsonPath('data.total_earning', null);
        $response->assertJsonPath('data.total_referral_earning', null);
        $response->assertJsonPath('data.total_tip_received', null);
        $response->assertJsonPath('data.total_withdraw', null);
        $response->assertJsonPath('data.total_balance', null);
        $response->assertJsonPath('data.total_bet', null);
        $response->assertJsonPath('data.user.show_name', false);
        $response->assertJsonPath('data.user.show_total_earning', false);
        $response->assertJsonPath('data.user.show_total_referral_earning', false);
        $response->assertJsonPath('data.user.show_total_tip_received', false);
        $response->assertJsonPath('data.user.show_total_withdraw', false);
        $response->assertJsonPath('data.user.show_total_balance', false);
        $response->assertJsonPath('data.user.show_total_bet', false);
    }

    public function test_user_always_sees_own_hidden_name_and_private_totals(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Visible',
            'middle_name' => 'To',
            'last_name' => 'Self',
            'show_name' => false,
            'show_total_earning' => false,
            'show_total_referral_earning' => false,
            'show_total_tip_received' => false,
            'show_total_withdraw' => false,
            'show_total_balance' => false,
            'show_total_bet' => false,
        ]);
        $user->assignRole(UserRole::USER);
        $user->userBalance()->create([
            'total_earning' => 125.50,
            'total_referral_earning' => 25.25,
            'total_tip_received' => 10.75,
            'total_withdraw' => 40.00,
            'total_balance' => 60.00,
            'total_bet' => 15.00,
        ]);

        $response = $this->actingAs($user, 'api')
            ->getJson("/api/show_artist_prifile/{$user->id}");

        $response->assertOk();
        $response->assertJsonPath('data.user.name', 'Visible To Self');

        $data = $response->json('data');

        $this->assertSame(125.50, (float) $data['total_earning']);
        $this->assertSame(25.25, (float) $data['total_referral_earning']);
        $this->assertSame(10.75, (float) $data['total_tip_received']);
        $this->assertSame(40.00, (float) $data['total_withdraw']);
        $this->assertSame(60.00, (float) $data['total_balance']);
        $this->assertSame(15.00, (float) $data['total_bet']);
    }

    public function test_me_route_shows_hidden_profile_fields_to_authenticated_user(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Private',
            'middle_name' => 'Me',
            'last_name' => 'User',
            'show_email' => false,
            'show_name' => false,
            'show_total_earning' => false,
            'show_total_referral_earning' => false,
            'show_total_tip_received' => false,
            'show_total_withdraw' => false,
        ]);
        $user->assignRole(UserRole::USER);
        $user->userBalance()->create([
            'total_earning' => 300.50,
            'total_referral_earning' => 75.25,
            'total_tip_received' => 40.75,
            'total_withdraw' => 110.00,
        ]);

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/me');

        $response->assertOk();
        $response->assertJsonPath('data.user.email', $user->email);
        $response->assertJsonPath('data.user.name', 'Private Me User');
        $response->assertJsonPath('data.user.show_email', false);
        $response->assertJsonPath('data.user.show_name', false);
        $response->assertJsonPath('data.user.show_total_earning', false);
        $response->assertJsonPath('data.user.show_total_referral_earning', false);
        $response->assertJsonPath('data.user.show_total_tip_received', false);
        $response->assertJsonPath('data.user.show_total_withdraw', false);

        $data = $response->json('data');

        $this->assertSame(300.50, (float) $data['total_earning']);
        $this->assertSame(75.25, (float) $data['total_referral_earning']);
        $this->assertSame(40.75, (float) $data['total_tip_received']);
        $this->assertSame(110.00, (float) $data['total_withdraw']);
    }

    public function test_regular_user_profile_can_be_viewed_with_the_profile_endpoint(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::USER);
        $user->userBalance()->create();

        $viewer = User::factory()->create();
        $viewer->assignRole(UserRole::USER);

        $response = $this->actingAs($viewer, 'api')
            ->getJson("/api/show_artist_prifile/{$user->id}");

        $response->assertOk();
        $response->assertJsonPath('data.user.id', $user->id);
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

    public function test_user_can_update_profile_visibility_fields(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::USER);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/profile/visibility', [
                'show_email' => false,
                'show_name' => false,
                'show_total_earning' => false,
                'show_total_referral_earning' => false,
                'show_total_tip_received' => false,
                'show_total_withdraw' => false,
                'show_total_balance' => false,
                'show_total_bet' => false,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.show_email', false);
        $response->assertJsonPath('data.show_name', false);
        $response->assertJsonPath('data.show_total_earning', false);
        $response->assertJsonPath('data.show_total_referral_earning', false);
        $response->assertJsonPath('data.show_total_tip_received', false);
        $response->assertJsonPath('data.show_total_withdraw', false);
        $response->assertJsonPath('data.show_total_balance', false);
        $response->assertJsonPath('data.show_total_bet', false);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'show_email' => false,
            'show_name' => false,
            'show_total_earning' => false,
            'show_total_referral_earning' => false,
            'show_total_tip_received' => false,
            'show_total_withdraw' => false,
            'show_total_balance' => false,
            'show_total_bet' => false,
        ]);
    }

    public function test_profile_visibility_update_requires_a_visibility_field(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::USER);

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/profile/visibility', []);

        $response->assertStatus(422);
    }

    public function test_toggle_email_visibility_requires_authentication(): void
    {
        $response = $this->postJson('/api/profile/toggle-email-visibility', ['show_email' => false]);

        $response->assertUnauthorized();
    }

    public function test_profile_visibility_update_requires_authentication(): void
    {
        $response = $this->postJson('/api/profile/visibility', ['show_name' => false]);

        $response->assertUnauthorized();
    }
}
