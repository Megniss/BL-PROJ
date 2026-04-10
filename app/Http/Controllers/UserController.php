<?php

namespace App\Http\Controllers;

use App\Models\SwapRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function search(Request $request)
    {
        $search = $request->query('search', '');

        $blockedIds = ($authUser = auth('sanctum')->user())
            ? $authUser->blockedUserIds()
            : collect();

        $users = User::withCount(['books' => fn($q) => $q->where('status', 'Available')])
            ->where('is_blocked', false)
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($blockedIds->isNotEmpty(), fn($q) => $q->whereNotIn('id', $blockedIds))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'created_at']);

        // pieliek grāmatu priekšskatījumu katram
        $users->each(function ($user) {
            $user->preview_books = $user->books()
                ->where('status', 'Available')
                ->latest()
                ->limit(4)
                ->get(['id', 'cover_image', 'genre']);
        });

        return response()->json($users);
    }

    // publiskais profils
    public function show(User $user)
    {
        $authUser = auth('sanctum')->user();

        $books = $user->books()
            ->where('status', '!=', 'Pending')
            ->withAvg('ratings', 'stars')
            ->withCount('ratings')
            ->latest()
            ->get();

        $swapsCount = SwapRequest::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)->orWhere('owner_id', $user->id);
            })
            ->count();

        // apmaiņu vēsture tikai ja lietotājs atļāvis rādīt
        $swapHistory = [];
        if ($user->show_swap_history) {
            $swapHistory = SwapRequest::where('status', 'accepted')
                ->where(function ($q) use ($user) {
                    $q->where('requester_id', $user->id)->orWhere('owner_id', $user->id);
                })
                ->with(['offeredBook:id,title,author', 'wantedBook:id,title,author'])
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn($s) => [
                    'gave'     => $s->requester_id === $user->id ? $s->offeredBook?->title : $s->wantedBook?->title,
                    'received' => $s->requester_id === $user->id ? $s->wantedBook?->title : $s->offeredBook?->title,
                    'date' => $s->updated_at->toDateString(),
                ]);
        }

        $iBlockedThem = $authUser
            ? $authUser->blocking()->where('blocked_id', $user->id)->exists()
            : false;

        $theyBlockedMe = $authUser
            ? $authUser->blockedBy()->where('blocker_id', $user->id)->exists()
            : false;

        return response()->json([
            'id'   => $user->id,
            'name' => $user->name,
            'joined'       => $user->show_joined ? $user->created_at->toDateString() : null,
            'books'        => $books->count(),
            'swaps'        => $user->show_swaps ? $swapsCount : null,
            'swap_history' => $swapHistory,
            'library'      => $books,
            'is_blocked'   => $iBlockedThem,
            'they_blocked_me' => $theyBlockedMe,
        ]);
    }
}
