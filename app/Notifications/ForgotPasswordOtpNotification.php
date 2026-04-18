<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForgotPasswordOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $otp
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset Password')
            ->greeting('Hello!')
            ->line('You requested to reset your password.')
            ->line('Your One-Time Password (OTP) is:')
            ->line("**{$this->otp}**")
            ->line('This OTP will expire in 10 minutes.')
            ->line('If you did not request a password reset, please ignore this email.')
            ->line('Thank you.');
    }
}
