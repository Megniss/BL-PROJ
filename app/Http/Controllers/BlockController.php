<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    public function index(Request $request)
    {
        $blocked = $request->user()->blocking()->with('blocked:id,name')->get();
        return response()->json($blocked->map(fn($b) => $b->blocked)->values());
    }

    public function store(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot block yourself.'], 422);
        }

        $request->user()->blocking()->firstOrCreate(['blocked_id' => $user->id]);

        return response()->json(['blocked' => true]);
    }

    public function destroy(Request $request, User $user)
    {
        $request->user()->blocking()->where('blocked_id', $user->id)->delete();

        return response()->json(['blocked' => false]);
    }
}
