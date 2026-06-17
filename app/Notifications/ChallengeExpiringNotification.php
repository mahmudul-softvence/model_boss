<?php

namespace App\Notifications;

use App\Models\Challenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ChallengeExpiringNotification extends Notification implements ShouldQueue
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
        return 'challenge.expired';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'type' => 'challenge.expired',
            'challenge_id' => $this->challenge->id,
            'challenge_no' => $this->challenge->challenge_no,
            'amount' => $this->challenge->amount,
            'message' => 'Your challenge offer expired and the reserved amount has been refunded.',
        ];
    }
}
