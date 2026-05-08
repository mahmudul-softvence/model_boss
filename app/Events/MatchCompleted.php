<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $userIds;
    public int $matchId;
    public string $message;

    public function __construct(array $userIds, int $matchId, string $winnerName)
    {
        $this->userIds = $userIds;
        $this->matchId = $matchId;

        $this->message = "The match is over. Winner is {$winnerName}";
    }

    public function broadcastOn(): array
    {
        return collect($this->userIds)
            ->map(fn (int $id) => new PrivateChannel('user.' . $id))
            ->all();
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->matchId,
            'message'  => $this->message,
        ];
    }

    public function broadcastAs(): string
    {
        return 'match.completed';
    }
}