<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Rating;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\SwapAccepted;
use App\Notifications\SwapDeclined;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function users()
    {
        $users = User::withCount('books')
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
            ]);

        return response()->json($users);
    }

    public function books()
    {
        $books = Book::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($b) => [
                'id' => $b->id,
                'title' => $b->title,
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
        // can't block another admin or yourself
        if ($user->is_admin || $user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot block this user.'], 422);
        }

        $user->update(['is_blocked' => true]);
        // revoke all tokens so they get logged out
        $user->tokens()->delete();

        return response()->json(['message' => 'User blocked.']);
    }

    public function unblockUser(User $user)
    {
        $user->update(['is_blocked' => false]);
        return response()->json(['message' => 'User unblocked.']);
    }

    public function makeAdmin(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You are already an admin.'], 422);
        }

        $user->update(['is_admin' => true, 'is_blocked' => false]);
        return response()->json(['message' => 'Admin rights granted.']);
    }

    public function removeAdmin(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot remove your own admin rights.'], 422);
        }

        $user->update(['is_admin' => false]);
        return response()->json(['message' => 'Admin rights removed.']);
    }

    public function deleteBook(Book $book)
    {
        $book->delete();
        return response()->json(['message' => 'Book deleted.']);
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

    public function acceptSwap(SwapRequest $swap)
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

        User::find($swap->requester_id)->notify(new SwapAccepted($swap));

        return response()->json($swap->fresh(['offeredBook', 'wantedBook', 'requester:id,name']));
    }

    public function declineSwap(SwapRequest $swap)
    {
        if ($swap->status !== 'pending') {
            return response()->json(['message' => 'This request is no longer pending.'], 422);
        }

        DB::transaction(function () use ($swap) {
            Book::where('id', $swap->offered_book_id)->update(['status' => 'Available']);
            Book::where('id', $swap->wanted_book_id)->update(['status' => 'Available']);
            $swap->update(['status' => 'declined']);
        });

        User::find($swap->requester_id)->notify(new SwapDeclined($swap));

        return response()->json($swap->fresh(['offeredBook', 'wantedBook', 'requester:id,name']));
    }

    public function deleteSwap(SwapRequest $swap)
    {
        // free books if still pending
        if ($swap->status === 'pending') {
            Book::where('id', $swap->offered_book_id)->update(['status' => 'Available']);
            Book::where('id', $swap->wanted_book_id)->update(['status' => 'Available']);
        }
        $swap->delete();
        return response()->json(['message' => 'Swap request deleted.']);
    }

    public function ratings()
    {
        $ratings = Rating::with([
            'book:id,title,author',
            'rater:id,name',
        ])->latest()->get()->map(fn($r) => [
            'id' => $r->id,
            'book' => $r->book?->title,
            'author' => $r->book?->author,
            'rater' => $r->rater?->name,
            'stars' => $r->stars,
            'review' => $r->review,
            'date' => $r->created_at->toDateString(),
        ]);

        return response()->json($ratings);
    }
}
