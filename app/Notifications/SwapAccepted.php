<?php

namespace App\Notifications;

use App\Models\SwapRequest;
use Illuminate\Notifications\Notification;

class SwapAccepted extends Notification
{
    public function __construct(public SwapRequest $swap) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
}
