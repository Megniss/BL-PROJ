<?php

namespace App\Support;

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// dati e-pastiem (katrs veids ir atsevišķs e-pasts, sk. TYPES)
// kopējie saraksti ir vienādi visiem, tāpēc tos rēķina vienreiz (global),
// "tuvumā" katram lietotājam savs (nearby)
class Digest
{
    public const DAYS = 30;
    public const NEARBY_KM = 25;
    public const LIMIT = 6;

    // e-pastu veidi: jaunas grāmatas tuvumā, visvairāk samainītās, populārākie autori, labāk novērtētās
    public const TYPES = ['nearby', 'popular', 'authors', 'rated'];

    // šīs grāmatas nerādām (admins dzēsa vai pārbauda)
    private const HIDDEN = ['Removed', 'UnderReview'];

    public static function global(): array
    {
        $since = now()->subDays(self::DAYS);

        // ja pēdējā mēnesī nekā nav, ņemam visu laiku
        $books = self::popularBooks($since);
        if ($books === []) {
            $books = self::popularBooks(null);
        }

        $authors = self::popularAuthors($since);
        if ($authors === []) {
            $authors = self::popularAuthors(null);
        }

        return [
            'week' => [
                'books' => Book::where('created_at', '>=', now()->subDays(7))->whereNotIn('status', self::HIDDEN)->count(),
                'swaps' => DB::table('swap_requests')->where('status', 'accepted')->where('updated_at', '>=', now()->subDays(7))->count(),
                'available' => Book::where('status', 'Available')->count(),
            ],
            'popular' => $books,
            'authors' => $authors,
            'rated' => self::topRated(),
        ];
    }

    // visvairāk samainītās - skaita grāmatas, ko cilvēki izvēlējās (wanted)
    private static function popularBooks($since): array
    {
        return DB::table('swap_requests as s')
            ->join('books as b', 'b.id', '=', 's.wanted_book_id')
            ->where('s.status', 'accepted')
            ->whereNotIn('b.status', self::HIDDEN)
            ->when($since, fn($q) => $q->where('s.updated_at', '>=', $since))
            ->selectRaw('max(b.title) as title, max(b.author) as author, max(b.cover_image) as cover, count(*) as cnt')
            ->groupByRaw('lower(b.title), lower(b.author)')
            ->orderByDesc('cnt')
            ->orderBy('title')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn($r) => [
                'title' => $r->title,
                'meta' => $r->author,
                'badge' => $r->cnt . ($r->cnt == 1 ? ' swap' : ' swaps'),
                'cover' => self::cover($r->cover),
                'cover_file' => self::coverFile($r->cover),
                'url' => self::bookUrl($r->title, $r->author),
            ])
            ->all();
    }

    private static function popularAuthors($since): array
    {
        return DB::table('swap_requests as s')
            ->join('books as b', 'b.id', '=', 's.wanted_book_id')
            ->where('s.status', 'accepted')
            ->whereNotIn('b.status', self::HIDDEN)
            ->when($since, fn($q) => $q->where('s.updated_at', '>=', $since))
            ->selectRaw('max(b.author) as author, count(*) as cnt, count(distinct lower(b.title)) as titles')
            ->groupByRaw('lower(b.author)')
            ->orderByDesc('cnt')
            ->orderBy('author')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn($r) => [
                'title' => $r->author,
                'meta' => $r->titles . ($r->titles == 1 ? ' book' : ' different books'),
                'badge' => $r->cnt . ($r->cnt == 1 ? ' swap' : ' swaps'),
                // autoram - visas viņa grāmatas
                'url' => url('/browse') . '?' . http_build_query(['search' => $r->author]),
            ])
            ->all();
    }

    // labāk novērtētās, vismaz 2 vērtējumi, lai viens 5 neuzvar
    private static function topRated(): array
    {
        return DB::table('ratings as r')
            ->join('books as b', 'b.id', '=', 'r.book_id')
            ->whereNotIn('b.status', self::HIDDEN)
            ->selectRaw('max(b.title) as title, max(b.author) as author, max(b.cover_image) as cover, avg(r.stars) as avg_stars, count(*) as cnt')
            ->groupByRaw('lower(b.title), lower(b.author)')
            ->havingRaw('count(*) >= 2')
            ->orderByDesc('avg_stars')
            ->orderByDesc('cnt')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn($r) => [
                'title' => $r->title,
                'meta' => $r->author . ' · ' . $r->cnt . ' ratings',
                'badge' => '★ ' . number_format((float) $r->avg_stars, 1),
                'cover' => self::cover($r->cover),
                'cover_file' => self::coverFile($r->cover),
                'url' => self::bookUrl($r->title, $r->author),
            ])
            ->all();
    }

    // jaunas grāmatas tuvumā (pēdējās 7 dienas), tikai ja lietotājam ir atrašanās vieta
    public static function nearby(User $user): array
    {
        if (!$user->hasLocation()) {
            return [];
        }

        $distance = Distance::sql($user, 'owners.latitude', 'owners.longitude');
        $blocked = $user->blockedUserIds();

        return Book::query()
            ->join('users as owners', 'owners.id', '=', 'books.user_id')
            ->where('books.status', 'Available')
            ->where('books.created_at', '>=', now()->subDays(7))
            ->where('books.user_id', '!=', $user->id)
            ->where('owners.is_blocked', false)
            ->whereNull('owners.deleted_at')
            ->whereNotNull('owners.latitude')
            ->when($blocked->isNotEmpty(), fn($q) => $q->whereNotIn('books.user_id', $blocked))
            ->whereRaw("$distance <= " . Distance::limit(self::NEARBY_KM))
            ->select('books.title', 'books.author', 'books.cover_image')
            ->selectRaw("$distance as dist")
            ->orderBy('dist')
            ->orderByDesc('books.created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn($b) => [
                'title' => $b->title,
                'meta' => $b->author,
                'badge' => Distance::toKm((float) $b->dist) . ' km',
                'cover' => self::cover($b->cover_image),
                'cover_file' => self::coverFile($b->cover_image),
                'url' => self::bookUrl($b->title, $b->author),
            ])
            ->all();
    }

    // saraksts vienam e-pasta veidam, tukšs = nesūtām
    public static function items(string $type, User $user, array $global): array
    {
        return $type === 'nearby' ? self::nearby($user) : $global[$type];
    }

    // saite uz lapu, kur uzreiz atveras šīs grāmatas logs (Browse.vue lasa title un author)
    private static function bookUrl(string $title, string $author): string
    {
        return url('/browse') . '?' . http_build_query(['title' => $title, 'author' => $author]);
    }

    // vākiem vajag pilnu adresi, citādi e-pastā bilde neatvērsies
    private static function cover(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    // faila ceļš diskā, lai bildi var pielikt e-pastam (sk. DigestEmail::embedImages)
    private static function coverFile(?string $path): ?string
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }
}
