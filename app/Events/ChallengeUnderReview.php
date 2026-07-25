<?php

namespace App\Events;

use App\Models\Challenge;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChallengeUnderReview implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        protected Challenge $challenge,
        protected array $userIds,
    ) {}

    public function broadcastOn(): array
    {
        return collect($this->userIds)
            ->map(fn (int $id) => new PrivateChannel('user.'.$id))
            ->all();
    }

    public function broadcastWith(): array
    {
        return [
            'challenge_id' => $this->challenge->id,
            'challenge_no' => $this->challenge->challenge_no,
            'message' => 'A challenge result has been submitted for review.',
        ];
    }

    public function broadcastAs(): string
    {
        return 'challenge.under_review';
    }
}
