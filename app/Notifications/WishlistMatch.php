<?php

namespace App\Notifications;

use App\Models\Book;
use App\Support\Md;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// kāds pievienoja grāmatu, kas ir lietotāja vēlmju sarakstā
// e-pastu sūta tikai ja $mail = true (max reizi dienā par vienu grāmatu)
class WishlistMatch extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Book $book, public bool $mail = true) {}

    public function via(object $notifiable): array
    {
        return $this->mail ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "\"{$this->book->title}\" from your wishlist is now available.",
            'book' => $this->book->title,
            'author' => $this->book->author,
            'owner_name' => $this->book->user?->name,
            'book_id' => $this->book->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/browse?' . http_build_query(['title' => $this->book->title, 'author' => $this->book->author]));

        return (new MailMessage)
            ->subject('BookLoop - A book from your wishlist is available')
            ->greeting("Hi " . Md::escape($notifiable->name) . "!")
            ->line("Good news - a book you were looking for was just added:")
            ->line("**" . Md::escape($this->book->title) . "** by " . Md::escape($this->book->author))
            ->line("Owner: " . Md::escape($this->book->user?->name))
            ->action('View the book', $url)
            ->line('You can remove it from your wishlist in My Library.');
    }
}
