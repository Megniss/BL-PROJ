<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// vienreizējais kods pieteikšanās apstiprināšanai
class TwoFactorCode extends Notification
{
    public function __construct(public string $code, public int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("BookLoop — Your code: {$this->code}")
            ->greeting("Hi {$notifiable->name}!")
            ->line('Your BookLoop verification code is:')
            ->line("**{$this->code}**")
            ->line("It is valid for {$this->minutes} minutes. If you did not try to sign in, change your password.")
            ->line('Stay safe, the BookLoop team.');
    }
}
