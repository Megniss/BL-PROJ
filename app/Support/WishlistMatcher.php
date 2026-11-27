<?php

namespace App\Support;

use App\Models\Block;
use App\Models\Book;
use App\Models\User;
use App\Models\Wishlist;
use App\Notifications\WishlistMatch;
use Illuminate\Support\Facades\DB;

// kad pievieno jaunu grāmatu - paziņo tiem, kam tā ir vēlmju sarakstā
class WishlistMatcher
{
    // e-pastu par vienu un to pašu grāmatu ne biežāk kā reizi dienā
    public const MAIL_EVERY_HOURS = 24;

    // atslēga grāmatai - nosaukums|autors mazajiem burtiem
    public static function key(string $title, string $author): string
    {
        return mb_strtolower($title) . '|' . mb_strtolower($author);
    }

    // kas meklē šīs grāmatas (My Library "♥ 3 meklē")
    // atgriež [atslēga => ['count' => 3, 'users' => [{id, name}]]]
    // users tikai tie, kas atļāvuši rādīt savu sarakstu, pārējos tikai saskaita
    public static function wantedBy(User $owner, $books): array
    {
        if ($books->isEmpty()) {
            return [];
        }

        $keys = $books->map(fn($b) => self::key($b->title, $b->author))->unique();
        $titles = $books->map(fn($b) => mb_strtolower($b->title))->unique()->values()->all();
        $blocked = $owner->blockedUserIds();

        $wishes = Wishlist::with('user:id,name,show_wishlist')
            ->whereIn(DB::raw('lower(title)'), $titles)
            ->where('user_id', '!=', $owner->id)
            ->whereNotIn('user_id', $blocked)
            ->whereHas('user', fn($q) => $q->where('is_blocked', false)->whereNull('deleted_at'))
            ->oldest()
            ->get();

        $out = [];
        foreach ($wishes as $wish) {
            $key = self::key($wish->title, $wish->author);
            if (!$keys->contains($key)) {
                continue;
            }
            $out[$key] ??= ['count' => 0, 'users' => []];
            $out[$key]['count']++;
            if ($wish->user->show_wishlist) {
                $out[$key]['users'][] = ['id' => $wish->user->id, 'name' => $wish->user->name];
            }
        }

        return $out;
    }

    public static function bookAdded(Book $book): int
    {
        if ($book->status !== 'Available') {
            return 0;
        }

        $book->loadMissing('user');

        // bloķētie abos virzienos ar grāmatas īpašnieku
        $blocked = Block::where('blocker_id', $book->user_id)->pluck('blocked_id')
            ->merge(Block::where('blocked_id', $book->user_id)->pluck('blocker_id'));

        $wishes = Wishlist::with('user')
            ->whereRaw('lower(title) = ?', [mb_strtolower($book->title)])
            ->whereRaw('lower(author) = ?', [mb_strtolower($book->author)])
            ->where('user_id', '!=', $book->user_id)
            ->whereNotIn('user_id', $blocked)
            ->whereHas('user', fn($q) => $q->where('is_blocked', false)->whereNull('deleted_at'))
            ->get();

        $sent = 0;
        foreach ($wishes as $wish) {
            $mail = !$wish->notified_at || $wish->notified_at->lt(now()->subHours(self::MAIL_EVERY_HOURS));

            try {
                $wish->user->notify(new WishlistMatch($book, $mail));
            } catch (\Exception $e) {
                // e-pasts neaizgāja - grāmatu tik un tā saglabājam
                report($e);
            }

            if ($mail) {
                $wish->update(['notified_at' => now()]);
            }
            $sent++;
        }

        return $sent;
    }
}
