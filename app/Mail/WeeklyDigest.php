<?php

namespace App\Mail;

use App\Models\User;
use App\Support\Digest;
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
        $week = $this->global['week'];

        return new Content(markdown: 'mail.weekly-digest', with: [
            'name' => $this->user->name,
            'stats' => [
                ['value' => $week['books'], 'label' => $week['books'] == 1 ? 'new book' : 'new books'],
                ['value' => $week['swaps'], 'label' => $week['swaps'] == 1 ? 'swap' : 'swaps'],
                ['value' => $week['available'], 'label' => 'available to swap'],
            ],
            'sections' => $this->sections(),
            'unsubscribeUrl' => self::unsubscribeUrl($this->user),
        ]);
    }

    // tukšās sadaļas izlaižam, lai režģī nav caurumu
    private function sections(): array
    {
        $sections = [];

        if ($this->nearby !== []) {
            $sections[] = ['icon' => '📍', 'title' => 'New near you', 'items' => $this->nearby];
        } elseif (!$this->user->hasLocation()) {
            $sections[] = [
                'icon' => '📍',
                'title' => 'Books near you',
                'text' => 'Add your location and we will show you new books within ' . Digest::NEARBY_KM . ' km.',
                'link' => ['url' => url('/settings'), 'label' => 'Add location'],
            ];
        }

        $titles = [
            'popular' => ['🔁', 'Most swapped'],
            'authors' => ['✍️', 'Popular authors'],
            'rated' => ['⭐', 'Top rated'],
        ];
        foreach ($titles as $key => [$icon, $title]) {
            if ($this->global[$key] !== []) {
                $sections[] = ['icon' => $icon, 'title' => $title, 'items' => $this->global[$key]];
            }
        }

        return $sections;
    }

    // parakstīta saite bez termiņa, lai var atrakstīties bez ielogošanās
    // oneClick - POST no gmail/outlook pogas, citādi saite e-pasta apakšā
    public static function unsubscribeUrl(User $user, bool $oneClick = false): string
    {
        return URL::signedRoute($oneClick ? 'digest.unsubscribe.oneclick' : 'digest.unsubscribe', ['user' => $user->id]);
    }
}
