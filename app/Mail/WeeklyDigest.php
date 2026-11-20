<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

// iknedēļas apkopojums: populārākās grāmatas, autori, vērtējumi, jaunumi tuvumā
class WeeklyDigest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public array $global,
        public array $nearby,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'BookLoop - Your weekly book roundup 📚');
    }

    // gmail un outlook rāda "unsubscribe" pogu augšā, ja ir šie headeri
    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<' . self::unsubscribeUrl($this->user, true) . '>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.weekly-digest', with: [
            'name' => $this->user->name,
            'week' => $this->global['week'],
            'popular' => $this->global['popular'],
            'authors' => $this->global['authors'],
            'rated' => $this->global['rated'],
            'nearby' => $this->nearby,
            'hasLocation' => $this->user->hasLocation(),
            'unsubscribeUrl' => self::unsubscribeUrl($this->user),
        ]);
    }

    // parakstīta saite bez termiņa, lai var atrakstīties bez ielogošanās
    // oneClick - POST no gmail/outlook pogas, citādi saite e-pasta apakšā
    public static function unsubscribeUrl(User $user, bool $oneClick = false): string
    {
        return URL::signedRoute($oneClick ? 'digest.unsubscribe.oneclick' : 'digest.unsubscribe', ['user' => $user->id]);
    }
}
