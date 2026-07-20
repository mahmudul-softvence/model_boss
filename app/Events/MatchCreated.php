<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $message;

    public $rules;

    public $matchId;

    protected $userIds;

    protected $playerIds;

    public function __construct($userIds, $message, $playerIds, $rules = null, $matchId = null)
    {
        $this->userIds = $userIds;
        $this->message = $message;
        $this->playerIds = $playerIds;
        $this->rules = $rules;
        $this->matchId = $matchId;
    }

    public function broadcastOn()
    {
        return collect($this->userIds)->map(function ($id) {
            return new PrivateChannel('user.'.$id);
        })->toArray();
    }

    public function broadcastWith()
    {
        return [
            'match_id' => $this->matchId,
            'message' => $this->message,
            'rules' => $this->rules,
            'player_ids' => $this->playerIds,
        ];
    }

    public function broadcastAs()
    {
        return 'match.created';
    }
}
