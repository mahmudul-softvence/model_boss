<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Tests\TestCase;

class UserSelectListTest extends TestCase
{
    public function test_it_returns_ten_users_for_select(): void
    {
        $users = User::factory()
            ->count(12)
            ->sequence(fn (Sequence $sequence): array => [
                'artist_name' => 'Artist '.$sequence->index,
                'created_at' => now()->subMinutes(12 - $sequence->index),
            ])
            ->create();

        $response = $this->getJson('/api/get_users_for_select');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.id', $users->last()->id)
            ->assertJsonPath('data.0.artist', 'Artist 11')
            ->assertJsonMissing(['email' => $users->last()->email]);
    }

    public function test_it_filters_users_by_search_term(): void
    {
        $matchingUser = User::factory()->create([
            'artist_name' => 'Needle Beats',
        ]);

        User::factory()->create([
            'artist_name' => 'Different Artist',
        ]);

        $response = $this->getJson('/api/get_users_for_select?search=Needle');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingUser->id)
            ->assertJsonPath('data.0.artist', 'Needle Beats');
    }

    public function test_it_returns_null_artist_when_artist_name_is_null(): void
    {
        $user = User::factory()->create([
            'artist_name' => null,
        ]);

        $response = $this->getJson('/api/get_users_for_select');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $user->id)
            ->assertJsonPath('data.0.artist', null);
    }
}
