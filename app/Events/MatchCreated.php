<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MatchCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $message;
    protected $userIds;

    public function __construct($userIds)
    {
        $this->message = "New match available! Go to home to support your favorite player.";
        $this->userIds = $userIds;
    }

    public function broadcastOn()
    {
        return collect($this->userIds)->map(function ($id) {
            return new PrivateChannel('user.' . $id);
        })->toArray();
    }

    public function broadcastAs()
    {
        return 'match.created';
    }
}
