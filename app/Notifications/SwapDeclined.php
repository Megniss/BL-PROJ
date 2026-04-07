<?php

namespace App\Notifications;

use App\Models\SwapRequest;
use Illuminate\Notifications\Notification;

class SwapDeclined extends Notification
{
    public function __construct(public SwapRequest $swap) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
}
