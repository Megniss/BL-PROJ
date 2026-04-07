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
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($blockedIds->isNotEmpty(), fn($q) => $q->whereNotIn('id', $blockedIds))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'created_at']);

        // pieliec īsus grāmatu priekšskatījumus
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
                $q->where('requester_id', $user->id)
                  ->orWhere('owner_id', $user->id);
            })
            ->count();

        $isBlocked = $authUser
            ? $authUser->blocking()->where('blocked_id', $user->id)->exists()
            : false;

        return response()->json([
            'id'         => $user->id,
            'name'       => $user->name,
            'joined'     => $user->show_joined ? $user->created_at->toDateString() : null,
            'books'      => $books->count(),
            'swaps'      => $user->show_swaps ? $swapsCount : null,
            'library'    => $books,
            'is_blocked' => $isBlocked,
        ]);
    }
}
