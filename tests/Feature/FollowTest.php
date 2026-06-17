<?php

namespace Tests\Feature;

use App\Models\Follower;
use App\Models\User;
use App\Notifications\NewFollowerNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_following_a_user_sends_notification_to_that_user()
    {
        Notification::fake();

        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson("/api/follow/{$other->id}")
            ->assertStatus(200);

        Notification::assertSentTo(
            $other,
            NewFollowerNotification::class,
            function (NewFollowerNotification $notification, array $channels) use ($other, $user) {
                $this->assertEqualsCanonicalizing(['mail', 'database', 'broadcast'], $notification->via($other));

                return in_array('mail', $channels)
                    && in_array('database', $channels)
                    && in_array('broadcast', $channels)
                    && $notification->toDatabase($other)['follower_id'] === $user->id;
            }
        );
    }

    public function test_following_again_does_not_send_duplicate_notification()
    {
        Notification::fake();

        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user, 'api')->postJson("/api/follow/{$other->id}")->assertStatus(200);
        $this->actingAs($user, 'api')->postJson("/api/follow/{$other->id}")->assertStatus(200);

        Notification::assertSentToTimes($other, NewFollowerNotification::class, 1);
    }

    public function test_unfollow_then_follow_again_does_not_resend_notification()
    {
        Notification::fake();

        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user, 'api')->postJson("/api/follow/{$other->id}")->assertStatus(200);
        $this->actingAs($user, 'api')->deleteJson("/api/unfollow/{$other->id}")->assertStatus(200);

        // The follow row is restored, so re-following must not notify again.
        $this->actingAs($user, 'api')->postJson("/api/follow/{$other->id}")->assertStatus(200);

        Notification::assertSentToTimes($other, NewFollowerNotification::class, 1);
    }

    public function test_unfollow_soft_deletes_the_follow_row()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user, 'api')->postJson("/api/follow/{$other->id}")->assertStatus(200);
        $this->actingAs($user, 'api')->deleteJson("/api/unfollow/{$other->id}")->assertStatus(200);

        // Row remains, only soft-deleted.
        $this->assertDatabaseHas('followers', [
            'follower_id' => $user->id,
            'following_id' => $other->id,
        ]);
        $this->assertNotNull(
            Follower::withTrashed()
                ->where('follower_id', $user->id)
                ->where('following_id', $other->id)
                ->value('deleted_at')
        );

        // Re-following restores the same row (no duplicate, counts back to 1).
        $this->actingAs($user, 'api')->postJson("/api/follow/{$other->id}")->assertStatus(200);

        $this->assertEquals(1, Follower::where('follower_id', $user->id)
            ->where('following_id', $other->id)->count());

        $other->refresh();
        $this->assertEquals(1, $other->followers_count);
    }

    public function test_follow_counts_and_idempotency()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson("/api/follow/{$other->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('followers', [
            'follower_id' => $user->id,
            'following_id' => $other->id,
        ]);

        $user->refresh();
        $other->refresh();

        $this->assertEquals(1, $user->following_count);
        $this->assertEquals(1, $other->followers_count);

        // Call follow again - should be idempotent
        $this->actingAs($user, 'api')
            ->postJson("/api/follow/{$other->id}")
            ->assertStatus(200);

        $user->refresh();
        $other->refresh();

        $this->assertEquals(1, $user->following_count);
        $this->assertEquals(1, $other->followers_count);
    }

    public function test_see_follower_includes_is_following()
    {
        $user = User::factory()->create();
        $follower = User::factory()->create();
        $another = User::factory()->create();

        // follower follows user
        $follower->following()->attach($user->id);
        $follower->increment('following_count');
        $user->increment('followers_count');

        // user follows another (not follower)
        $user->following()->attach($another->id);
        $user->increment('following_count');
        $another->increment('followers_count');

        $this->actingAs($user, 'api')
            ->getJson('/api/followers/list')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['followers']]);

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/followers/list')
            ->json();

        // find follower in response and ensure is_following is false
        $found = collect($response['data']['followers']['data'])->firstWhere('id', $follower->id);
        $this->assertNotNull($found);
        $this->assertFalse($found['is_following']);
    }

    public function test_see_following_includes_is_followed_by()
    {
        $user = User::factory()->create();
        $following = User::factory()->create();
        $mutual = User::factory()->create();

        // user follows following
        $user->following()->attach($following->id);
        $user->increment('following_count');
        $following->increment('followers_count');

        // mutual follow
        $user->following()->attach($mutual->id);
        $user->increment('following_count');
        $mutual->increment('followers_count');

        $mutual->following()->attach($user->id);
        $mutual->increment('following_count');
        $user->increment('followers_count');

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/following/list')
            ->json();

        $found = collect($response['data']['following']['data'])->firstWhere('id', $mutual->id);
        $this->assertNotNull($found);
        $this->assertTrue($found['is_followed_by']);
    }

    public function test_count_endpoints_return_correct_values()
    {
        $user = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $a->following()->attach($user->id);
        $a->increment('following_count');
        $user->increment('followers_count');

        $user->following()->attach($b->id);
        $user->increment('following_count');
        $b->increment('followers_count');

        $resp = $this->actingAs($user, 'api')->getJson('/api/followers/count')->assertStatus(200)->json();
        $this->assertEquals(1, $resp['data']['followers_count']);

        $resp2 = $this->actingAs($user, 'api')->getJson('/api/following/count')->assertStatus(200)->json();
        $this->assertEquals(1, $resp2['data']['following_count']);
    }
}
