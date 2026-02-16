<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SupportPlaced implements ShouldBroadcastNow
{
    use SerializesModels;

    public $data;
    public $matchId;

    public function __construct($data, $matchId)
    {
        $this->data = $data;
        $this->matchId = $matchId;

        // Log::info('SupportPlaced Constructor Data:', [
        //     'data' => $this->data,
        //     'matchId' => $this->matchId,
        // ]);
    }

    public function broadcastOn()
    {
        return new Channel('match.' . $this->matchId);
    }

    public function broadcastAs()
    {
        return 'support.placed';
    }
}
