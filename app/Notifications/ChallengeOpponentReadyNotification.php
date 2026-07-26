<?php

namespace App\Notifications;

use App\Models\Challenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChallengeOpponentReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Challenge $challenge,
        protected string $readyPlayerName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your opponent is ready on Model Boss')
            ->view('emails.challenge-opponent-ready', [
                'notifiable_name' => $notifiable->name,
                'opponent_name' => $this->readyPlayerName,
                'challenge_no' => $this->challenge->challenge_no,
                'ready_expires_at' => $this->challenge->ready_expires_at,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload($notifiable);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload($notifiable));
    }

    public function broadcastType(): string
    {
        return 'challenge.opponent_ready';
    }

    protected function payload(object $notifiable): array
    {
        return [
            'type' => 'challenge.opponent_ready',
            'challenge_id' => $this->challenge->id,
            'challenge_no' => $this->challenge->challenge_no,
            'opponent_name' => $this->readyPlayerName,
            'ready_expires_at' => $this->challenge->ready_expires_at?->toIso8601String(),
            'message' => "{$this->readyPlayerName} is ready. You have 10 minutes to join.",
        ];
    }
}
