<?php

namespace App\Support;

use App\Models\Block;
use App\Models\Book;
use App\Models\Wishlist;
use App\Notifications\WishlistMatch;

// kad pievieno jaunu grāmatu - paziņo tiem, kam tā ir vēlmju sarakstā
class WishlistMatcher
{
    // e-pastu par vienu un to pašu grāmatu ne biežāk kā reizi dienā
    public const MAIL_EVERY_HOURS = 24;

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
