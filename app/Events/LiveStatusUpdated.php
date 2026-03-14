<?php

namespace App\Events;

use App\Models\CheckLiveStatus;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $liveStatus;

    public function __construct(CheckLiveStatus $liveStatus)
    {
        $this->liveStatus = $liveStatus;
    }

    /**
     * The channel the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('live-status-updates'),
        ];
    }

    /**
     * The name the event should be broadcast as (optional).
     */
    public function broadcastAs(): string
    {
        return 'status.changed';
    }
}
