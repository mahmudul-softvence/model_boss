<?php

namespace App\Notifications;

use App\Models\Challenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChallengeLostNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Challenge $challenge, protected float $stake) {}

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
            ->subject('Your challenge result on Model Boss')
            ->view('emails.challenge-lost', [
                'notifiable_name' => $notifiable->name,
                'opponent_name' => $this->opponentName($notifiable),
                'challenge_no' => $this->challenge->challenge_no,
                'stake' => $this->stake,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
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
        return 'challenge.lost';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(object $notifiable): array
    {
        return [
            'type' => 'challenge.lost',
            'challenge_id' => $this->challenge->id,
            'challenge_no' => $this->challenge->challenge_no,
            'opponent_name' => $this->opponentName($notifiable),
            'stake' => $this->stake,
            'message' => 'You lost this challenge. '.number_format($this->stake, 2).' coins from your stake were forfeited.',
        ];
    }

    /**
     * Resolve the other player's display name relative to the recipient.
     */
    protected function opponentName(object $notifiable): string
    {
        $opponent = $this->challenge->challenger_id === $notifiable->id
            ? $this->challenge->acceptor
            : $this->challenge->challenger;

        return $opponent?->artist_name ?: ($opponent?->name ?: 'your opponent');
    }
}
