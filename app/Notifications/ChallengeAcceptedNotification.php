<?php

namespace App\Notifications;

use App\Models\Challenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ChallengeAcceptedNotification extends Notification implements ShouldQueue
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
        return 'challenge.accepted';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'type' => 'challenge.accepted',
            'challenge_id' => $this->challenge->id,
            'challenge_no' => $this->challenge->challenge_no,
            'accepted_by_user_id' => $this->challenge->accepted_by_user_id,
            'accepted_by_name' => $this->challenge->acceptor?->name,
            'amount' => $this->challenge->amount,
            'message' => 'Your challenge has been accepted.',
        ];
    }
}
