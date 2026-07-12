<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    public function test_owner_can_update_their_post(): void
    {
        $owner = User::factory()->create();
        $post = Post::create([
            'user_id' => $owner->id,
            'image' => 'posts/example.jpg',
            'description' => 'Original description',
        ]);

        $this->actingAs($owner, 'api')
            ->putJson("/api/posts/{$post->id}", [
                'description' => 'Updated description',
            ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Post updated successfully.']);

        $this->assertSame('Updated description', $post->fresh()->description);
    }

    public function test_non_owner_cannot_update_a_post(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $post = Post::create([
            'user_id' => $owner->id,
            'image' => 'posts/example.jpg',
            'description' => 'Original description',
        ]);

        $this->actingAs($intruder, 'api')
            ->putJson("/api/posts/{$post->id}", [
                'description' => 'Hijacked description',
            ])
            ->assertStatus(403)
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertSame('Original description', $post->fresh()->description);
    }

    public function test_owner_can_delete_their_post(): void
    {
        $owner = User::factory()->create();
        $post = Post::create([
            'user_id' => $owner->id,
            'image' => 'posts/example.jpg',
            'description' => 'To be deleted',
        ]);

        $this->actingAs($owner, 'api')
            ->deleteJson("/api/posts/{$post->id}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Post deleted successfully.']);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_non_owner_cannot_delete_a_post(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $post = Post::create([
            'user_id' => $owner->id,
            'image' => 'posts/example.jpg',
            'description' => 'Keep me',
        ]);

        $this->actingAs($intruder, 'api')
            ->deleteJson("/api/posts/{$post->id}")
            ->assertStatus(403)
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }
}
