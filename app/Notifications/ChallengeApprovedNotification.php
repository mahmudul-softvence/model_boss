<?php

namespace App\Notifications;

use App\Models\Challenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ChallengeApprovedNotification extends Notification implements ShouldQueue
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
        return 'challenge.approved';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'type' => 'challenge.approved',
            'challenge_id' => $this->challenge->id,
            'challenge_no' => $this->challenge->challenge_no,
            'amount' => $this->challenge->amount,
            'message' => 'Your challenge is approved and now live for players to accept.',
        ];
    }
}
