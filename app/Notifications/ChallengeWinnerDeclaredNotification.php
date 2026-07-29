<?php

namespace App\Notifications;

use App\Models\Challenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChallengeWinnerDeclaredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Challenge $challenge) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You won your challenge on Model Boss')
            ->view('emails.challenge-winner-declared', [
                'notifiable_name' => $notifiable->name,
                'opponent_name' => $this->opponentName($notifiable),
                'challenge_no' => $this->challenge->challenge_no,
                'amount' => $this->challenge->amount,
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
        return 'challenge.won';
    }

    protected function payload(object $notifiable): array
    {
        return [
            'type' => 'challenge.won',
            'challenge_id' => $this->challenge->id,
            'challenge_no' => $this->challenge->challenge_no,
            'opponent_name' => $this->opponentName($notifiable),
            'payout' => (float) $this->challenge->amount,
            'message' => 'You won challenge #' . $this->challenge->challenge_no . '! Your payout of '
                . number_format((float) $this->challenge->amount * 2 * 0.85, 2)
                . ' coins will be available within 2 days. Claim it early from the challenge page.',
        ];
    }

    protected function opponentName(object $notifiable): string
    {
        $opponent = $this->challenge->challenger_id === $notifiable->id
            ? $this->challenge->acceptor
            : $this->challenge->challenger;

        return $opponent?->artist_name ?: ($opponent?->name ?: 'your opponent');
    }
}
