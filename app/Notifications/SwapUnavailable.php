<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// otrs lietotājs izdzēsa kontu, apmaiņa vairs nav iespējama
class SwapUnavailable extends Notification
{
    use Queueable;

    public function __construct(public string $bookTitle) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Swap is no longer available. \"{$this->bookTitle}\" is available again.",
        ];
    }
}
