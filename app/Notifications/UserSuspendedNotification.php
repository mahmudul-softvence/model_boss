<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserSuspendedNotification extends Notification  implements ShouldQueue
{
    use Queueable;

    public $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $message = (new MailMessage)
            ->subject('Your Account Has Been Suspended')
            ->greeting('Hello ' . $this->user->name . ',')
            ->line('Your account has been suspended.')
            ->line('Reason: ' . $this->user->suspension_reason);

        if ($this->user->suspension_note) {
            $message->line('Note: ' . $this->user->suspension_note);
        }

        if ($this->user->is_permanent_suspended) {
            $message->line('Duration: Permanent');
        } else {
            $message->line('Suspension ends: ' . $this->user->suspended_until->format('Y-m-d H:i'));
        }

        $message->line('Please contact support if you think this is a mistake.');

        return $message;
    }
}
