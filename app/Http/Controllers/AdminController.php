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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // lai nesanāk copy paste katrā metodē
    private function log(Request $request, string $action, string $type, int|null $id, string $name, string $reason = null): void
    {
        AdminLog::create([
            'admin_id'    => $request->user()->id,
            'action'      => $action,
            'target_type' => $type,
            'target_id'   => $id,
            'target_name' => $name,
            'reason' => $reason,
        ]);
    }

    public function users()
    {
        $users = User::withCount('books')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email'      => $u->email,
                'is_admin'   => $u->is_admin,
                'is_blocked' => $u->is_blocked,
                'books_count' => $u->books_count,
                'joined' => $u->created_at->toDateString(),
            ]);

        return response()->json($users);
    }

    public function books()
    {
        $books = Book::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($b) => [
                'id'     => $b->id,
                'title'  => $b->title,
                'author' => $b->author,
                'genre' => $b->genre,
                'status' => $b->status,
                'owner' => $b->user?->name,
                'created_at' => $b->created_at->toDateString(),
            ]);

        return response()->json($books);
    }

    public function blockUser(Request $request, User $user)
    {
        // adminiem un sev nevar bloķēt
        if ($user->is_admin || $user->id === $request->user()->id) {
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
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You are already an admin.'], 422);
        }

        $user->update(['is_admin' => true, 'is_blocked' => false]);
        $this->log($request, 'make_admin', 'user', $user->id, $user->name);
        return response()->json(['message' => 'Admin rights granted.']);
    }

    public function removeAdmin(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot remove your own admin rights.'], 422);
        }

        $user->update(['is_admin' => false]);
        $this->log($request, 'remove_admin', 'user', $user->id, $user->name);
        return response()->json(['message' => 'Admin rights removed.']);
    }

    public function deleteBook(Request $request, Book $book)
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $owner  = $book->user;
        $title  = $book->title;
        $author = $book->author;

        $book->delete();

        $this->log($request, 'delete_book', 'book', null, $title, $request->reason);

        // grāmata dzēsta — pazino īpašnieku
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
            'requester:id,name',
            'offeredBook:id,title,author',
            'wantedBook:id,title,author',
        ])->latest()->get();

        return response()->json($swaps);
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
            $swap->update(['status' => 'accepted']);

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

                SwapRequest::whereIn('id', $conflicting->pluck('id'))->update(['status' => 'declined']);
                if ($bookIdsToFree->isNotEmpty()) {
                    Book::whereIn('id', $bookIdsToFree)->update(['status' => 'Available']);
                }
            }
        });

        try {
            User::find($swap->requester_id)->notify(new SwapAccepted($swap));
        } catch (\Exception $e) {}
        $this->log($request, 'accept_swap', 'swap', $swap->id, "{$swap->offeredBook->title} ↔ {$swap->wantedBook->title}");

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
            $swap->update(['status' => 'declined']);
        });

        try {
            User::find($swap->requester_id)->notify(new SwapDeclined($swap));
        } catch (\Exception $e) {}
        $this->log($request, 'decline_swap', 'swap', $swap->id, "{$swap->offeredBook->title} ↔ {$swap->wantedBook->title}");

        return response()->json($swap->fresh(['offeredBook', 'wantedBook', 'requester:id,name']));
    }

    public function deleteSwap(Request $request, SwapRequest $swap)
    {
        $label = "{$swap->offeredBook->title} ↔ {$swap->wantedBook->title}";

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
                'id'          => $l->id,
                'admin'       => $l->admin?->name,
                'action'      => $l->action,
                'target_type' => $l->target_type,
                'target_name' => $l->target_name,
                'reason' => $l->reason,
                'date'   => $l->created_at->format('Y-m-d H:i'),
            ]);

        return response()->json($logs);
    }

    public function ratings()
    {
        $ratings = Rating::with([
            'book:id,title,author',
            'rater:id,name',
        ])->latest()->get()->map(fn($r) => [
            'id'     => $r->id,
            'book'   => $r->book?->title,
            'author' => $r->book?->author,
            'rater'  => $r->rater?->name,
            'stars' => $r->stars,
            'review' => $r->review,
            'date' => $r->created_at->toDateString(),
        ]);

        return response()->json($ratings);
    }
}
