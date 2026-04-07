<?php

namespace App\Http\Controllers;

use App\Models\SwapRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $booksCount = $user->books()->count();
        $swapsCount = SwapRequest::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                  ->orWhere('owner_id', $user->id);
            })
            ->count();

        return response()->json([
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'joined'      => $user->created_at->toDateString(),
            'books'       => $booksCount,
            'swaps'       => $swapsCount,
            'show_joined' => $user->show_joined,
            'show_swaps'  => $user->show_swaps,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'current_password' => ['nullable', 'string'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'new_password_confirmation' => ['nullable', 'string'],
        ]);

        if (!empty($data['new_password'])) {
            if (empty($data['current_password']) || !Hash::check($data['current_password'], $user->password)) {
                return response()->json([
                    'errors' => ['current_password' => ['Current password is incorrect.']],
                ], 422);
            }
            $user->password = Hash::make($data['new_password']);
        }

        $user->name  = $data['name'];
        $user->email = $data['email'];
        $user->save();

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ]);
    }

    public function updatePrivacy(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'show_joined' => ['required', 'boolean'],
            'show_swaps'  => ['required', 'boolean'],
        ]);

        $user->show_joined = $data['show_joined'];
        $user->show_swaps  = $data['show_swaps'];
        $user->save();

        return response()->json(['show_joined' => $user->show_joined, 'show_swaps' => $user->show_swaps]);
    }

    public function history(Request $request)
    {
        $user = $request->user();

        $history = SwapRequest::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)
                  ->orWhere('owner_id', $user->id);
            })
            ->with(['requester:id,name', 'offeredBook', 'wantedBook', 'ratings'])
            ->latest()
            ->paginate(10);

        return response()->json($history);
    }

    public function notifications(Request $request)
    {
        return response()->json($request->user()->notifications()->latest()->paginate(15));
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'All marked as read.']);
    }
}
