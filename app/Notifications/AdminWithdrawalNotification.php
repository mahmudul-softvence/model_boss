<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminWithdrawalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $withdraw;

    protected $user;

    /**
     * Create a new notification instance.
     */
    public function __construct(Withdrawal $withdraw, User $user)
    {
        $this->withdraw = $withdraw;
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Withdrawal Request')
            ->line('A new withdrawal request has been submitted.')
            ->line('User: '.$this->user->name)
            ->line('Amount: '.$this->withdraw->coin_amount)
            ->line('Thank you.');
    }

    /**
     * Database representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        return [
            'type' => 'admin.withdrawal.created',
            'withdraw_id' => $this->withdraw->id,
            'withdraw_no' => $this->withdraw->withdraw_no,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'coin_amount' => $this->withdraw->coin_amount,
            'usd_amount' => $this->withdraw->usd_amount,
            'status' => $this->withdraw->status,
            'message' => 'New withdrawal request submitted.',
        ];
    }

    /**
     * Broadcast representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return BroadcastMessage
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'type' => 'admin.withdrawal.created',
            'withdraw_id' => $this->withdraw->id,
            'withdraw_no' => $this->withdraw->withdraw_no,
            'user_name' => $this->user->name,
            'coin_amount' => $this->withdraw->coin_amount,
            'usd_amount' => $this->withdraw->usd_amount,
            'message' => 'New withdrawal request submitted.',
        ]);
    }

    /**
     * Custom broadcast event name
     *
     * @return string
     */
    public function broadcastType()
    {
        return 'admin.withdrawal.created';
    }
}
