<?php

namespace App\Notifications;

use App\Models\SwapRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// kad maiņas pieprasījums pieņemts
class SwapAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SwapRequest $swap) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message'        => "Your swap request was accepted! You received \"{$this->swap->wantedBook->title}\".",
            'wanted_book'    => $this->swap->wantedBook->title,
            'offered_book'   => $this->swap->offeredBook->title,
            'swap_id'        => $this->swap->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('BookLoop — Swap Accepted!')
            ->greeting("Hi {$notifiable->name}!")
            ->line("Great news — your swap request was accepted.")
            ->line("You received: **{$this->swap->wantedBook->title}** by {$this->swap->wantedBook->author}")
            ->line("You gave away: **{$this->swap->offeredBook->title}** by {$this->swap->offeredBook->author}")
            ->action('Go to BookLoop', url('/dashboard'))
            ->line('Thanks for using BookLoop!');
    }
}
