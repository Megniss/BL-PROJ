<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// sūta tikai vienu reizi kamēr nav izlasīts
class NewMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $sender, public string $preview) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'      => 'message',
            'message'   => "{$this->sender->name} sent you a message.",
            'sender_id' => $this->sender->id,
            'preview'   => mb_strimwidth($this->preview, 0, 60, '…'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("BookLoop — New message from {$this->sender->name}")
            ->greeting("Hi {$notifiable->name}!")
            ->line("{$this->sender->name} sent you a message:")
            ->line("> " . mb_strimwidth($this->preview, 0, 120, '…'))
            ->action('Reply on BookLoop', url('/messages'))
            ->line('Thanks for using BookLoop!');
    }
}
