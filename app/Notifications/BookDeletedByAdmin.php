<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookDeletedByAdmin extends Notification
{
    public function __construct(
        public string $bookTitle,
        public string $bookAuthor,
        public string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Your book \"{$this->bookTitle}\" was removed by an admin. Reason: {$this->reason}",
            'book'    => $this->bookTitle,
            'reason'  => $this->reason,
            'type'    => 'admin_action',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('BookLoop — Book Removed')
            ->greeting("Hi {$notifiable->name}!")
            ->line("Your book **\"{$this->bookTitle}\"** by {$this->bookAuthor} has been removed by an administrator.")
            ->line("**Reason:** {$this->reason}")
            ->line('If you believe this was a mistake, please contact support.')
            ->action('Go to BookLoop', url('/dashboard'));
    }
}
