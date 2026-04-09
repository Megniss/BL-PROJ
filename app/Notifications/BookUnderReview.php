<?php

namespace App\Notifications;

use App\Models\Book;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookUnderReview extends Notification
{

    public function __construct(public Book $book, public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message'  => "Your book \"{$this->book->title}\" has been placed under review. Reason: {$this->reason}",
            'book_id'  => $this->book->id,
            'book'     => $this->book->title,
            'reason'   => $this->reason,
            'type'     => 'admin_action',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('BookLoop — Book Under Review')
            ->greeting("Hi {$notifiable->name}!")
            ->line("Your book **\"{$this->book->title}\"** by {$this->book->author} has been placed under review by an administrator.")
            ->line("**Reason:** {$this->reason}")
            ->line('It is temporarily hidden from the catalog while under review. You will be notified once the review is complete.')
            ->action('Go to BookLoop', url('/dashboard'))
            ->line('If you have questions, please contact support.');
    }
}
