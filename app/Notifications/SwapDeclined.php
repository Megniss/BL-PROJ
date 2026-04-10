<?php

namespace App\Notifications;

use App\Models\SwapRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// pieprasījums noraidīts, grāmata atkal brīva
class SwapDeclined extends Notification implements ShouldQueue
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
            'message'      => "Your swap request was declined. \"{$this->swap->offeredBook->title}\" is available again.",
            'wanted_book'  => $this->swap->wantedBook->title,
            'offered_book' => $this->swap->offeredBook->title,
            'swap_id'      => $this->swap->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('BookLoop — Swap Request Declined')
            ->greeting("Hi {$notifiable->name}!")
            ->line("Unfortunately your swap request was declined.")
            ->line("You wanted: **{$this->swap->wantedBook->title}** by {$this->swap->wantedBook->author}")
            ->line("Your book **{$this->swap->offeredBook->title}** is available again.")
            ->action('Browse Other Books', url('/browse'))
            ->line('Thanks for using BookLoop!');
    }
}
