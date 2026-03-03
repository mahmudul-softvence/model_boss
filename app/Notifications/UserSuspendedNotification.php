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
            ->line('Reason: ' . $this->user->suspension->reason);

        if ($this->user->suspension->note) {
            $message->line('Note: ' . $this->user->suspension->note);
        }

        if ($this->user->suspension->is_permanent) {
            $message->line('Duration: Permanent');
        } else {
            $message->line('Suspension ends: ' . $this->user->suspension->suspended_until
                ? $this->user->suspension->suspended_until->format('d M Y')
                : 'N/A');
        }

        $message->line('Please contact support if you think this is a mistake.');

        return $message;
    }
}
