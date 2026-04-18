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

    protected $userIds;

    protected $playerIds;

    public function __construct($userIds, $playerIds, $rules = null)
    {
        $this->message = 'New match available! Go to home to support your favorite player.';
        $this->rules = $rules;
        $this->userIds = $userIds;
        $this->playerIds = $playerIds;
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
