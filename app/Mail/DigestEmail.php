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

// viens e-pasts vienai tēmai (nearby, popular, authors, rated)
// katru veidu lietotājs ieslēdz/izslēdz atsevišķi iestatījumos
class DigestEmail extends Mailable
{
    use Queueable, SerializesModels;

    // teksti katram veidam
    public const COPY = [
        'nearby' => [
            'icon' => '📍',
            'subject' => 'New books near you',
            'title' => 'New books near you',
            'intro' => 'These books were added in the last 7 days within ' . Digest::NEARBY_KM . ' km of you.',
            'button' => 'See all new books near you',
            // jaunākās 25 km rādiusā
            'url' => '/browse?sort=newest&max_km=' . Digest::NEARBY_KM,
            'placeholder' => '📖',
            'link' => 'View book',
        ],
        'popular' => [
            'icon' => '🔁',
            'subject' => 'Most swapped books this month',
            'title' => 'Most swapped books',
            'intro' => 'These books changed hands the most in the last ' . Digest::DAYS . ' days.',
            'button' => 'See all most swapped books',
            'url' => '/browse?sort=most_swapped',
            'placeholder' => '📖',
            'link' => 'View book',
        ],
        'authors' => [
            'icon' => '✍️',
            'subject' => 'Popular authors on BookLoop',
            'title' => 'Popular authors',
            'intro' => 'Authors whose books people swapped the most in the last ' . Digest::DAYS . ' days.',
            // vairākus autorus vienā meklēšanā nevar, tāpēc grāmatas pēc apmaiņām - populāro autoru grāmatas būs augšā
            'button' => 'Books people swap the most',
            'url' => '/browse?sort=most_swapped',
            'placeholder' => '✍️',
            'link' => 'See their books',
        ],
        'rated' => [
            'icon' => '⭐',
            'subject' => 'Top rated books',
            'title' => 'Top rated books',
            'intro' => 'Books that readers rated the highest (at least 2 ratings).',
            'button' => 'See all top rated books',
            'url' => '/browse?sort=top_rated',
            'placeholder' => '📖',
            'link' => 'View book',
        ],
    ];

    public function __construct(
        public User $user,
        public string $type,
        public array $items,
        public array $week = [],
    ) {}

    public function envelope(): Envelope
    {
        $copy = self::COPY[$this->type];

        return new Envelope(subject: 'BookLoop - ' . $copy['subject'] . ' ' . $copy['icon']);
    }

    // gmail un outlook rāda "unsubscribe" pogu augšā, ja ir šie headeri
    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<' . self::unsubscribeUrl($this->user, $this->type, true) . '>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $copy = self::COPY[$this->type];

        return new Content(markdown: 'mail.digest', with: [
            'name' => $this->user->name,
            'copy' => $copy,
            'stats' => $this->stats(),
            'items' => $this->items,
            'buttonUrl' => url($copy['url']),
            'embedImages' => self::embedImages(),
            'unsubscribeUrl' => self::unsubscribeUrl($this->user, $this->type),
        ]);
    }

    // gmail/outlook bildes ielādē caur saviem serveriem, tie localhost neredz
    // tāpēc lokāli vākus pieliekam pašam e-pastam (cid), uz publiska servera - parastas saites
    // MAIL_EMBED_IMAGES=true/false var uzlikt ar roku
    public static function embedImages(): bool
    {
        $setting = config('mail.embed_images');
        if ($setting !== null) {
            return (bool) $setting;
        }

        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return in_array($host, ['localhost', '127.0.0.1', '0.0.0.0']) || str_ends_with($host, '.test');
    }

    // nedēļas skaitļi tikai "popular" e-pastā
    private function stats(): array
    {
        if ($this->type !== 'popular' || $this->week === []) {
            return [];
        }

        $w = $this->week;

        return [
            ['value' => $w['books'], 'label' => $w['books'] == 1 ? 'new book this week' : 'new books this week'],
            ['value' => $w['swaps'], 'label' => $w['swaps'] == 1 ? 'swap this week' : 'swaps this week'],
            ['value' => $w['available'], 'label' => 'available to swap'],
        ];
    }

    // parakstīta saite bez termiņa, lai var atrakstīties bez ielogošanās
    // atrakstās tikai no šī veida, pārējie paliek
    // oneClick - POST no gmail/outlook pogas, citādi saite e-pasta apakšā
    public static function unsubscribeUrl(User $user, string $type, bool $oneClick = false): string
    {
        return URL::signedRoute(
            $oneClick ? 'digest.unsubscribe.oneclick' : 'digest.unsubscribe',
            ['user' => $user->id, 'type' => $type],
        );
    }
}
