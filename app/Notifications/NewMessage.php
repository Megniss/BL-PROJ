<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class NewMessage extends Notification
{
    public function __construct(public User $sender, public string $preview) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
}
