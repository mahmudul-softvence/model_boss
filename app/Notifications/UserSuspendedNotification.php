<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserSuspendedNotification extends Notification implements ShouldQueue
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
        $suspension = $this->user->suspension;

        $message = (new MailMessage)
            ->subject('Your Account Has Been Suspended')
            ->greeting('Hello '.$this->user->name.',');

        if ($suspension->is_permanent) {

            $message->line('Your account has been suspended permanently.');
        } else {

            if ($suspension->suspended_until) {

                $days = round(now()->floatDiffInDays($suspension->suspended_until));

                $message->line("Your account has been suspended for {$days} days.")
                    ->line('Suspension ends on: '.$suspension->suspended_until->format('d M Y'));
            } else {

                $message->line('Your account has been temporarily suspended.');
            }
        }

        $message->line('Reason: '.$suspension->reason);

        if ($suspension->note) {
            $message->line('Note: '.$suspension->note);
        }

        $message->line('Please contact support if you think this is a mistake.')
            ->line('Thank you.');

        return $message;
    }
}
