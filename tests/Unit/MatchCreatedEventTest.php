<?php

namespace Tests\Unit;

use App\Events\MatchCreated;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\TestCase;

class MatchCreatedEventTest extends TestCase
{
    public function test_broadcast_payload_includes_the_match_id(): void
    {
        $event = new MatchCreated([5, 6], 'A match has been created.', [1, 2], 'Be on time.', 42);

        $payload = $event->broadcastWith();

        $this->assertSame(42, $payload['match_id']);
        $this->assertSame('A match has been created.', $payload['message']);
        $this->assertSame('Be on time.', $payload['rules']);
        $this->assertSame([1, 2], $payload['player_ids']);
    }

    public function test_match_id_is_null_when_not_provided(): void
    {
        $event = new MatchCreated([5], 'New match available.', [1, 2]);

        $this->assertNull($event->broadcastWith()['match_id']);
    }

    public function test_it_broadcasts_as_match_created_on_a_private_channel_per_user(): void
    {
        $event = new MatchCreated([5, 6], 'New match available.', [1, 2], null, 42);

        $channels = $event->broadcastOn();

        $this->assertSame('match.created', $event->broadcastAs());
        $this->assertCount(2, $channels);
        $this->assertContainsOnlyInstancesOf(PrivateChannel::class, $channels);
    }
}
