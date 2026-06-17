<?php

namespace App\Notifications;

use App\Models\Challenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ChallengeOfferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Challenge $challenge) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function broadcastType(): string
    {
        return 'challenge.offer';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'type' => 'challenge.offer',
            'challenge_id' => $this->challenge->id,
            'challenge_no' => $this->challenge->challenge_no,
            'challenger_id' => $this->challenge->challenger_id,
            'challenger_name' => $this->challenge->challenger?->name,
            'amount' => $this->challenge->amount,
            'message' => "You've been selected for a challenge. Accept?",
        ];
    }
}
