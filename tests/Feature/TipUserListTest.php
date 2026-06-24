<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TipUserListTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_list_can_search_by_artist_name(): void
    {
        $auth = User::factory()->create();
        $match = User::factory()->create(['artist_name' => 'Stage Star']);
        User::factory()->create(['artist_name' => 'Someone Else', 'name' => 'No Match']);

        $data = $this->actingAs($auth, 'api')
            ->getJson('/api/user-list?search=Stage')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($match->id, $data[0]['id']);
        $this->assertSame('Stage Star', $data[0]['name']);
    }

    public function test_user_list_name_prefers_artist_name_then_real_name(): void
    {
        $auth = User::factory()->create();

        $withArtist = User::factory()->create(['artist_name' => 'Stage Star']);
        $withoutArtist = User::factory()->create([
            'first_name' => 'Real',
            'middle_name' => null,
            'last_name' => 'Name',
            'artist_name' => null,
        ]);

        $data = collect(
            $this->actingAs($auth, 'api')
                ->getJson('/api/user-list')
                ->assertOk()
                ->json('data')
        )->keyBy('id');

        $this->assertSame('Stage Star', $data[$withArtist->id]['name']);
        $this->assertSame('Real Name', $data[$withoutArtist->id]['name']);
        $this->assertSame($withoutArtist->fresh()->name, $data[$withoutArtist->id]['name']);
    }
}
