<?php

namespace App\Notifications;

use App\Models\Challenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChallengeOfferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Challenge $challenge) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been challenged on Model Boss')
            ->view('emails.challenge-offer', [
                'notifiable_name' => $notifiable->name,
                'challenger_name' => $this->challenge->challenger?->name,
                'challenge_no' => $this->challenge->challenge_no,
                'amount' => $this->challenge->amount,
            ]);
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
