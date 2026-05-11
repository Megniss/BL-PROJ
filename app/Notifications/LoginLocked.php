<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// pārāk daudz neveiksmīgu pieteikšanās mēģinājumu
class LoginLocked extends Notification
{
    public function __construct(public int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('BookLoop — Login Attempt Warning')
            ->greeting("Hi {$notifiable->name}!")
            ->line('We detected too many failed login attempts on your account.')
            ->line("For your security, login has been temporarily blocked for **{$this->minutes} minutes**.")
            ->line('If this was you, simply wait and try again. If it was not you, consider changing your password after you regain access.')
            ->action('Go to BookLoop', url('/'))
            ->line('Stay safe, the BookLoop team.');
    }
}
