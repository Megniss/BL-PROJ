<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\Md;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

// sūta uz JAUNO e-pastu, e-pasts nomainās tikai pēc saites atvēršanas
class ConfirmNewEmail extends Notification
{
    const MINUTES = 60;

    public function __construct(public User $user, public string $email) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // saite parakstīta, e-pasts ir saitē, tāpēc to nevar samainīt
        $url = URL::temporarySignedRoute('email.confirm', now()->addMinutes(self::MINUTES), [
            'user' => $this->user->id,
            'email' => $this->email,
        ]);

        return (new MailMessage)
            ->subject('BookLoop - Confirm your new email')
            ->greeting('Hi ' . Md::escape($this->user->name) . '!')
            ->line('You asked to use this email address for your BookLoop account.')
            ->action('Confirm email', $url)
            ->line('The link is valid for ' . self::MINUTES . ' minutes. If it was not you, just ignore this email, nothing will change.')
            ->line('Stay safe, the BookLoop team.');
    }
}
