<?php

namespace App\Notifications;

use App\Models\Challenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ChallengeCreatedAdminNotification extends Notification implements ShouldQueue
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
        return 'admin.challenge.created';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'type' => 'admin.challenge.created',
            'challenge_id' => $this->challenge->id,
            'challenge_no' => $this->challenge->challenge_no,
            'challenger_id' => $this->challenge->challenger_id,
            'challenger_name' => $this->challenge->challenger?->name,
            'amount' => $this->challenge->amount,
            'mode' => $this->challenge->mode->value,
            'message' => 'A new challenge is awaiting your approval.',
        ];
    }
}
