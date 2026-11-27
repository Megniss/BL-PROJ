<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// admins atbildēja uz lietotāja atbalsta pieteikumu - tikai zvaniņā, bez e-pasta
// ja par šo pieteikumu jau ir nelasīts paziņojums, jaunu neveido (sk. ComplaintController::adminReply)
class SupportReply extends Notification
{
    use Queueable;

    public function __construct(public Complaint $complaint) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Support replied to your ticket \"{$this->complaint->subject}\".",
            'complaint_id' => $this->complaint->id,
            'subject' => $this->complaint->subject,
            'category' => $this->complaint->category,
        ];
    }
}
