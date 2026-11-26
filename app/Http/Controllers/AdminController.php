<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\Book;
use App\Models\Rating;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\BookDeletedByAdmin;
use App\Notifications\BookUnderReview;
use App\Notifications\SwapAccepted;
use App\Notifications\SwapDeclined;
use App\Support\LatvianCities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // lai nesanāk copy paste katrā metodē
    private function log(Request $request, string $action, string $type, int|null $id, string $name, ?string $reason = null): void
    {
        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => $action,
            'target_type' => $type,
            'target_id' => $id,
            'target_name' => $name,
            'reason' => $reason,
        ]);
    }

    public function users()
    {
        $users = User::withCount('books')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_admin' => $u->is_admin,
                'is_blocked' => $u->is_blocked,
                'books_count' => $u->books_count,
                'joined' => $u->created_at->toDateString(),
                'latitude' => $u->latitude,
                'longitude' => $u->longitude,
            ]);

        return response()->json($users);
    }

    public function books()
    {
        // apmaiņas pa grāmatām un statusiem, piem. [5 => ['pending' => 1, 'accepted' => 2]]
        // grāmata var būt gan piedāvātā, gan vēlamā, tāpēc skaita abas puses
        $swaps = [];
        foreach (['offered_book_id', 'wanted_book_id'] as $col) {
            $rows = SwapRequest::selectRaw("$col as book_id, status, count(*) as cnt")
                ->groupBy($col, 'status')
                ->get();
            foreach ($rows as $row) {
                $swaps[$row->book_id][$row->status] = ($swaps[$row->book_id][$row->status] ?? 0) + $row->cnt;
            }
        }

        // kāpēc grāmata pārbaudē - pēdējais admina iemesls
        $reviewReasons = AdminLog::where('action', 'review_book')
            ->orderBy('id')
            ->get(['target_id', 'reason'])
            ->pluck('reason', 'target_id');

        $books = Book::with('user:id,name,is_blocked,deleted_at')
            ->withAvg('ratings', 'stars')
            ->withCount('ratings')
            ->where('status', '!=', 'Removed')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($b) => [
                'id' => $b->id,
                'title' => $b->title,
                'author' => $b->author,
                'genre' => $b->genre,
                'language' => $b->language,
                'condition' => $b->condition,
                'status' => $b->status,
                'description' => $b->description,
                'cover_image' => $b->cover_image,
                'owner' => $this->swapUser($b->user),
                // kārtošanai pēc īpašnieka
                'owner_name' => $b->user?->name,
                'created_at' => $b->created_at,
                'swaps_pending' => $swaps[$b->id]['pending'] ?? 0,
                'swaps_accepted' => $swaps[$b->id]['accepted'] ?? 0,
                'rating_avg' => $b->ratings_avg_stars ? round($b->ratings_avg_stars, 1) : null,
                'rating_count' => $b->ratings_count,
                'review_reason' => $b->status === 'UnderReview' ? ($reviewReasons[$b->id] ?? null) : null,
            ]);

        return response()->json($books);
    }

    public function blockUser(Request $request, User $user)
    {
        // adminiem, sev un dzēstiem nevar bloķēt
        if ($user->is_admin || $user->deleted_at || $user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot block this user.'], 422);
        }

        $user->update(['is_blocked' => true]);
        $user->tokens()->delete();
        $this->log($request, 'block_user', 'user', $user->id, $user->name);

        return response()->json(['message' => 'User blocked.']);
    }

    public function unblockUser(Request $request, User $user)
    {
        $user->update(['is_blocked' => false]);
        $this->log($request, 'unblock_user', 'user', $user->id, $user->name);
        return response()->json(['message' => 'User unblocked.']);
    }

    public function makeAdmin(Request $request, User $user)
    {
        if ($user->id === $request->user()->id || $user->deleted_at) {
            return response()->json(['message' => 'Cannot make this user an admin.'], 422);
        }

        // pagaidu admin parole, jaunais admins to nomaina pirmajā ieejā
        $request->validate(['admin_password' => ['required', 'string', 'min:12']]);

        $user->forceFill([
            'is_admin' => true,
            'is_blocked' => false,
            'admin_password' => $request->admin_password,
            'admin_password_change' => true,
        ])->save();
        $this->log($request, 'make_admin', 'user', $user->id, $user->name);
        return response()->json(['message' => 'Admin rights granted.']);
    }

    public function removeAdmin(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot remove your own admin rights.'], 422);
        }

        $user->forceFill([
            'is_admin' => false,
            'admin_password' => null,
            'admin_password_change' => false,
        ])->save();

        // aizver admin paneli visās viņa sesijās
        $user->tokens()->update(['admin_until' => null]);
        $this->log($request, 'remove_admin', 'user', $user->id, $user->name);
        return response()->json(['message' => 'Admin rights removed.']);
    }

    public function deleteBook(Request $request, Book $book)
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $owner = $book->user;
        $title = $book->title;
        $author = $book->author;

        $book->delete();

        $this->log($request, 'delete_book', 'book', null, $title, $request->reason);

        // grāmata dzēsta - pazino īpašnieku
        if ($owner) {
            try {
                $owner->notify(new BookDeletedByAdmin($title, $author, $request->reason));
            } catch (\Exception $e) {}
        }

        return response()->json(['message' => 'Book deleted.']);
    }

    public function reviewBook(Request $request, Book $book)
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $book->update(['status' => 'UnderReview']);
        $this->log($request, 'review_book', 'book', $book->id, $book->title, $request->reason);
        try {
            $book->user->notify(new BookUnderReview($book, $request->reason));
        } catch (\Exception $e) {}
        return response()->json(['status' => 'UnderReview']);
    }

    public function unreviewBook(Request $request, Book $book)
    {
        $book->update(['status' => 'Available']);
        $this->log($request, 'unreview_book', 'book', $book->id, $book->title);
        return response()->json(['status' => 'Available']);
    }

    public function swaps()
    {
        $swaps = SwapRequest::with([
            'requester:id,name,is_blocked,deleted_at',
            'owner:id,name,is_blocked,deleted_at',
            'offeredBook:id,title,author,cover_image,status',
            'wantedBook:id,title,author,cover_image,status',
            'ratings.rater:id,name',
        ])->latest()->get();

        // viss vienā formā, lai Vue nav jāmeklē pa relācijām
        return response()->json($swaps->map(fn($s) => [
            'id' => $s->id,
            'status' => $s->status,
            'closed_by' => $s->closed_by,
            'created_at' => $s->created_at,
            'closed_at' => $s->closed_at,
            // cik dienas gaida (tikai gaidošajiem)
            'days_waiting' => $s->status === 'pending' ? (int) $s->created_at->diffInDays(now()) : null,
            'requester' => $this->swapUser($s->requester),
            'owner' => $this->swapUser($s->owner),
            // pieprasītājs piedāvā offered, īpašnieks atdod wanted
            'offered' => $this->swapBook($s->offeredBook),
            'wanted' => $this->swapBook($s->wantedBook),
            // paslēpts savā sarakstā (lietotājs nospieda "noņemt")
            'requester_dismissed' => (bool) $s->requester_dismissed,
            'owner_dismissed' => (bool) $s->owner_dismissed,
            'ratings' => $s->ratings->map(fn($r) => [
                'stars' => $r->stars,
                'review' => $r->review,
                'rater' => $r->rater?->name,
                'by_requester' => $r->rater_id === $s->requester_id,
            ])->values(),
        ]));
    }

    private function swapUser(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'blocked' => (bool) $user->is_blocked,
            'deleted' => $user->deleted_at !== null,
        ];
    }

    // grāmata var būt izdzēsta (admins dzēsa) - tad null
    private function swapBook(?Book $book): ?array
    {
        return $book ? $book->only(['id', 'title', 'author', 'cover_image', 'status']) : null;
    }

    public function acceptSwap(Request $request, SwapRequest $swap)
    {
        if ($swap->status !== 'pending') {
            return response()->json(['message' => 'This request is no longer pending.'], 422);
        }

        $requesterId = $swap->requester_id;
        $ownerId = $swap->owner_id;

        DB::transaction(function () use ($swap, $requesterId, $ownerId) {
            Book::where('id', $swap->offered_book_id)->update(['user_id' => $ownerId, 'status' => 'Available']);
            Book::where('id', $swap->wanted_book_id)->update(['user_id' => $requesterId, 'status' => 'Available']);
            $swap->update(['status' => 'accepted', ...SwapRequest::closedBy('admin')]);

            // noraida konfliktējošos
            $conflicting = SwapRequest::where('id', '!=', $swap->id)
                ->where('status', 'pending')
                ->where(function ($q) use ($swap) {
                    $q->whereIn('offered_book_id', [$swap->offered_book_id, $swap->wanted_book_id])
                      ->orWhereIn('wanted_book_id', [$swap->offered_book_id, $swap->wanted_book_id]);
                })->get(['id', 'offered_book_id', 'wanted_book_id']);

            if ($conflicting->isNotEmpty()) {
                $bookIdsToFree = $conflicting
                    ->flatMap(fn($s) => [$s->offered_book_id, $s->wanted_book_id])
                    ->unique()->diff([$swap->offered_book_id, $swap->wanted_book_id])->values();

                SwapRequest::whereIn('id', $conflicting->pluck('id'))->update(['status' => 'declined', ...SwapRequest::closedBy('system')]);
                if ($bookIdsToFree->isNotEmpty()) {
                    Book::whereIn('id', $bookIdsToFree)->update(['status' => 'Available']);
                }
            }
        });

        try {
            User::find($swap->requester_id)->notify(new SwapAccepted($swap));
        } catch (\Exception $e) {}
        $this->log($request, 'accept_swap', 'swap', $swap->id, ($swap->offeredBook?->title ?? "?") . " ↔ " . ($swap->wantedBook?->title ?? "?"));

        return response()->json($swap->fresh(['offeredBook', 'wantedBook', 'requester:id,name']));
    }

    public function declineSwap(Request $request, SwapRequest $swap)
    {
        if ($swap->status !== 'pending') {
            return response()->json(['message' => 'This request is no longer pending.'], 422);
        }

        DB::transaction(function () use ($swap) {
            Book::where('id', $swap->offered_book_id)->update(['status' => 'Available']);
            Book::where('id', $swap->wanted_book_id)->update(['status' => 'Available']);
            $swap->update(['status' => 'declined', ...SwapRequest::closedBy('admin')]);
        });

        try {
            User::find($swap->requester_id)->notify(new SwapDeclined($swap));
        } catch (\Exception $e) {}
        $this->log($request, 'decline_swap', 'swap', $swap->id, ($swap->offeredBook?->title ?? "?") . " ↔ " . ($swap->wantedBook?->title ?? "?"));

        return response()->json($swap->fresh(['offeredBook', 'wantedBook', 'requester:id,name']));
    }

    public function deleteSwap(Request $request, SwapRequest $swap)
    {
        $label = ($swap->offeredBook?->title ?? "?") . " ↔ " . ($swap->wantedBook?->title ?? "?");

        // atbrīvo grāmatas ja vēl pending
        if ($swap->status === 'pending') {
            Book::where('id', $swap->offered_book_id)->update(['status' => 'Available']);
            Book::where('id', $swap->wanted_book_id)->update(['status' => 'Available']);
        }
        $swap->delete();
        $this->log($request, 'delete_swap', 'swap', null, $label);

        return response()->json(['message' => 'Swap request deleted.']);
    }

    public function logs()
    {
        $logs = AdminLog::with('admin:id,name')
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'admin' => $l->admin?->name,
                'action' => $l->action,
                'target_type' => $l->target_type,
                'target_name' => $l->target_name,
                'reason' => $l->reason,
                'date' => $l->created_at->format('Y-m-d H:i'),
            ]);

        return response()->json($logs);
    }

    public function ratings()
    {
        $ratings = Rating::with([
            'book:id,title,author,cover_image,status',
            'rater:id,name,is_blocked,deleted_at',
            'swapRequest.requester:id,name,is_blocked,deleted_at',
            'swapRequest.owner:id,name,is_blocked,deleted_at',
        ])->latest()->get()->map(function ($r) {
            $swap = $r->swapRequest;
            // grāmatu atdeva otrs apmaiņas dalībnieks
            $giver = $swap && $r->rater_id === $swap->requester_id ? $swap->owner : $swap?->requester;

            return [
                'id' => $r->id,
                'stars' => $r->stars,
                'review' => $r->review,
                'date' => $r->created_at,
                'swap_id' => $swap?->id,
                'rater' => $this->swapUser($r->rater),
                'giver' => $this->swapUser($giver),
                'book' => $this->swapBook($r->book),
            ];
        });

        return response()->json($ratings);
    }

    // statistikas tabs - kopsummas, pēdējās 12 nedēļas, žanri un pilsētas
    public function stats()
    {
        $weeks = 12;
        $from = now()->startOfWeek()->subWeeks($weeks - 1);

        // nedēļas sākuma datums => skaits
        $bucket = function ($dates) use ($from, $weeks) {
            $out = [];
            for ($i = 0; $i < $weeks; $i++) $out[$from->copy()->addWeeks($i)->toDateString()] = 0;
            foreach ($dates as $d) {
                $key = $d->copy()->startOfWeek()->toDateString();
                if (isset($out[$key])) $out[$key]++;
            }
            return $out;
        };

        $users = User::whereNull('deleted_at')->get(['created_at', 'latitude', 'longitude']);
        $newUsers = $bucket($users->pluck('created_at')->filter()->filter(fn($d) => $d >= $from));
        $requests = $bucket(SwapRequest::where('created_at', '>=', $from)->pluck('created_at'));
        $accepted = $bucket(SwapRequest::where('status', 'accepted')->where('closed_at', '>=', $from)->pluck('closed_at'));

        $weekRows = [];
        foreach ($newUsers as $start => $n) {
            $weekRows[] = ['start' => $start, 'users' => $n, 'requests' => $requests[$start], 'accepted' => $accepted[$start]];
        }

        // žanri - top 8, pārējie kopā
        $genres = Book::where('status', '!=', 'Removed')
            ->selectRaw('genre, count(*) as cnt')
            ->groupBy('genre')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn($g) => ['name' => $g->genre ?: null, 'count' => (int) $g->cnt]);

        // pilsētas pēc tuvākās pilsētas koordinātēm
        $cities = [];
        $noLocation = 0;
        $elsewhere = 0;
        foreach ($users as $u) {
            if ($u->latitude === null || $u->longitude === null) {
                $noLocation++;
                continue;
            }
            $city = LatvianCities::nearest((float) $u->latitude, (float) $u->longitude);
            if ($city) $cities[$city] = ($cities[$city] ?? 0) + 1;
            else $elsewhere++;
        }
        arsort($cities);

        $ratings = Rating::selectRaw('avg(stars) as avg, count(*) as cnt')->first();

        return response()->json([
            'totals' => [
                'users' => $users->count(),
                'new_users_week' => end($newUsers),
                'books' => Book::where('status', '!=', 'Removed')->count(),
                'swaps_accepted' => SwapRequest::where('status', 'accepted')->count(),
                'swaps_pending' => SwapRequest::where('status', 'pending')->count(),
                'rating_avg' => $ratings->cnt ? round((float) $ratings->avg, 1) : null,
                'rating_count' => (int) $ratings->cnt,
            ],
            'weeks' => $weekRows,
            'genres' => $this->topWithOther($genres->all()),
            'cities' => $this->topWithOther(collect($cities)->map(fn($n, $c) => ['name' => $c, 'count' => $n])->values()->all()),
            'no_location' => $noLocation,
            'elsewhere' => $elsewhere,
        ]);
    }

    // pirmie 8, pārējie saskaitīti vienā "other" rindā (name = null nozīmē "cits")
    private function topWithOther(array $rows, int $top = 8): array
    {
        $named = array_values(array_filter($rows, fn($r) => $r['name'] !== null));
        $rest = array_sum(array_column(array_slice($named, $top), 'count'))
            + array_sum(array_column(array_filter($rows, fn($r) => $r['name'] === null), 'count'));

        $out = array_slice($named, 0, $top);
        if ($rest > 0) $out[] = ['name' => null, 'count' => $rest];

        return $out;
    }
}
