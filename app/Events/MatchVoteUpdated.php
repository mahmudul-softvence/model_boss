<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use App\Models\GameMatch;
use App\Models\PlayerVote;

class MatchVoteUpdated implements ShouldBroadcastNow
{
    use SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->data['match_id']);
    }

    public function broadcastAs()
    {
        return 'match.vote.updated';
    }

    public function broadcastWith()
    {
        return $this->data;
    }
}
