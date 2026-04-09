<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\SwapRequest;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'swap_request_id' => ['required', 'integer', 'exists:swap_requests,id'],
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:1000'],
        ]);

        $swap = SwapRequest::findOrFail($validated['swap_request_id']);

        if ($swap->status !== 'accepted') {
            return response()->json(['message' => 'Can only rate accepted swaps.'], 422);
        }

        $userId = $request->user()->id;

        // figure out which book this user actually received
        if ($userId === $swap->requester_id) {
            $bookId = $swap->wanted_book_id;
        } elseif ($userId === $swap->owner_id) {
            $bookId = $swap->offered_book_id;
        } else {
            return response()->json(['message' => 'You are not a participant in this swap.'], 403);
        }

        $alreadyRated = Rating::where('swap_request_id', $swap->id)
            ->where('book_id', $bookId)
            ->exists();

        if ($alreadyRated) {
            return response()->json(['message' => 'You have already rated this swap.'], 422);
        }

        $rating = Rating::create([
            'swap_request_id' => $swap->id,
            'book_id' => $bookId,
            'rater_id' => $userId,
            'stars' => $validated['stars'],
            'review' => $validated['review'] ?? null,
        ]);

        return response()->json($rating, 201);
    }
}
