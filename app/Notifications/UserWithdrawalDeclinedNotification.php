<?php

namespace App\Notifications;

use App\Models\Withdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserWithdrawalDeclinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $withdrawal;

    /**
     * @param  string  $reason
     */
    public function __construct(Withdrawal $withdrawal)
    {
        $this->withdrawal = $withdrawal;
    }

    /**
     * Delivery channels
     */
    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Mail: Notify user their request was rejected
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Withdrawal Declined – '.$this->withdrawal->withdraw_no)
            ->view('emails.withdrawal-declined', [
                'name' => $notifiable->name ?? $notifiable->email,
                'withdraw_no' => $this->withdrawal->withdraw_no,
                'coin_amount' => $this->withdrawal->coin_amount,
            ]);
    }

    /**
     * Database: Stored in 'notifications' table
     */
    public function toDatabase($notifiable)
    {
        return [
            'type' => 'user.withdrawal.declined',
            'withdraw_id' => $this->withdrawal->id,
            'withdraw_no' => $this->withdrawal->withdraw_no,
            'coin_amount' => $this->withdrawal->coin_amount,
            'status' => 'declined',
            'message' => 'Your withdrawal #'.$this->withdrawal->withdraw_no.' was declined.',
        ];
    }

    /**
     * Broadcast: Real-time alert in Next.js
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'user.withdrawal.declined',
            'withdraw_no' => $this->withdrawal->withdraw_no,
            'message' => 'Your withdrawal request has been declined.',
        ]);
    }

    public function broadcastType()
    {
        return 'user.withdrawal.declined';
    }
}
