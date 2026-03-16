<?php

namespace App\Notifications;

use App\Models\Withdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class UserWithdrawalCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $withdrawal;

    public function __construct(Withdrawal $withdrawal)
    {
        $this->withdrawal = $withdrawal;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Withdrawal Completed - ' . $this->withdrawal->withdraw_no)
            ->greeting('Hello!')
            ->line('Your withdrawal request has been successfully processed.')
            ->line('Withdrawal No: ' . $this->withdrawal->withdraw_no)
            ->line('Value: $' . $this->withdrawal->usd_amount)
            ->line('Thank you.');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'user.withdrawal.completed',
            'withdraw_no' => $this->withdrawal->withdraw_no,
            'coin_amount' => $this->withdrawal->coin_amount,
            'usd_amount' => $this->withdrawal->usd_amount,
            'message' => 'Your withdrawal #' . $this->withdrawal->withdraw_no . ' of ' . $this->withdrawal->coin_amount . ' coins is complete.',
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'user.withdrawal.completed',
            'withdraw_no' => $this->withdrawal->withdraw_no,
            'message' => 'Success! Your withdrawal has been completed.',
            'amount' => $this->withdrawal->coin_amount,
            'usd_amount' => $this->withdrawal->usd_amount,
        ]);
    }

    public function broadcastType()
    {
        return 'user.withdrawal.completed';
    }
}
