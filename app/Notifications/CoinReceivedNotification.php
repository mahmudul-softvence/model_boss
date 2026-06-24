<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoinReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected User $sender, protected float $amount) {}

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
            ->subject('You received coins on Model Boss')
            ->view('emails.coin-received', [
                'notifiable_name' => $notifiable->name,
                'sender_name' => $this->senderName(),
                'amount' => $this->amount,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
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
        return 'coin.received';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $senderName = $this->senderName();

        return [
            'type' => 'coin.received',
            'sender_id' => $this->sender->id,
            'sender_name' => $senderName,
            'amount' => $this->amount,
            'message' => $senderName.' sent you '.$this->amount.' coins.',
        ];
    }

    /**
     * Use the sender's artist name when available, otherwise their real name.
     */
    protected function senderName(): string
    {
        return $this->sender->artist_name ?: $this->sender->name;
    }
}
