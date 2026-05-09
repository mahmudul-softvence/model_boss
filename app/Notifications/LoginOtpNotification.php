<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $otp) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Login OTP Verification')
            ->greeting('Hello!')
            ->line('Your One-Time Password (OTP) for login is:')
            ->line("**{$this->otp}**")
            ->line('This OTP will expire in 10 minutes.')
            ->line('If you did not attempt to login, please ignore this email.')
            ->line('Thank you.');
    }
}
