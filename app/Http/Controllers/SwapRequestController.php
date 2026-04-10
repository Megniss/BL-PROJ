<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\SwapAccepted;
use App\Notifications\SwapDeclined;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SwapRequestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'offered_book_id' => ['required', 'integer', 'exists:books,id'],
            'wanted_book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $user = $request->user();
        $offeredBook = Book::findOrFail($data['offered_book_id']);
        $wantedBook = Book::findOrFail($data['wanted_book_id']);

        if ($offeredBook->user_id !== $user->id) {
            return response()->json(['message' => 'You do not own the offered book.'], 403);
        }

        if ($wantedBook->user_id === $user->id) {
            return response()->json(['message' => 'You cannot request your own book.'], 422);
        }

        if ($offeredBook->status !== 'Available' || $wantedBook->status !== 'Available') {
            return response()->json(['message' => 'One or both books are no longer available.'], 422);
        }

        // bloķētie nevar mainīties
        if ($user->blockedUserIds()->contains($wantedBook->user_id)) {
            return response()->json(['message' => 'You cannot swap with this user.'], 422);
        }

        $exists = SwapRequest::where('requester_id', $user->id)
            ->where('wanted_book_id', $wantedBook->id)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You already have a pending request for this book.'], 422);
        }

        $swap = DB::transaction(function () use ($offeredBook, $wantedBook, $user) {
            $offeredBook->update(['status' => 'Pending']);
            $wantedBook->update(['status' => 'Pending']);

            return SwapRequest::create([
                'requester_id' => $user->id,
                'owner_id' => $wantedBook->user_id,
                'offered_book_id' => $offeredBook->id,
                'wanted_book_id' => $wantedBook->id,
                'status' => 'pending',
            ]);
        });

        return response()->json($swap->load(['offeredBook', 'wantedBook', 'requester:id,name']), 201);
    }

    public function incoming(Request $request)
    {
        $bookIds = $request->user()->books()->pluck('id');

        $requests = SwapRequest::whereIn('wanted_book_id', $bookIds)
            ->where('owner_dismissed', false)
            ->with(['requester:id,name', 'offeredBook', 'wantedBook'])
            ->latest()
            ->get();

        return response()->json($requests);
    }

    public function outgoing(Request $request)
    {
        $requests = SwapRequest::where('requester_id', $request->user()->id)
            ->where('requester_dismissed', false)
            ->with(['offeredBook', 'wantedBook.user:id,name'])
            ->latest()
            ->get();

        return response()->json($requests);
    }

    public function destroy(Request $request, SwapRequest $swap)
    {
        $userId = $request->user()->id;
        $isRequester = $swap->requester_id === $userId;
        $isOwner = $swap->wantedBook->user_id === $userId || $swap->offeredBook->user_id === $userId;

        if (!$isRequester && !$isOwner) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($swap->status === 'pending') {
            if (!$isRequester) {
                return response()->json(['message' => 'Decline the request before dismissing it.'], 422);
            }
            // requester cancels their own pending request
            DB::transaction(function () use ($swap) {
                Book::where('id', $swap->offered_book_id)->update(['status' => 'Available']);
                Book::where('id', $swap->wanted_book_id)->update(['status' => 'Available']);
                $swap->update(['status' => 'declined', 'requester_dismissed' => true]);
            });
            return response()->json(['message' => 'Request cancelled.']);
        }

        if ($isRequester) {
            $swap->update(['requester_dismissed' => true]);
        } else {
            $swap->update(['owner_dismissed' => true]);
        }

        return response()->json(['message' => 'Dismissed.']);
    }

    public function accept(Request $request, SwapRequest $swap)
    {
        if ($swap->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($swap->status !== 'pending') {
            return response()->json(['message' => 'This request is no longer pending.'], 422);
        }

        $requesterId = $swap->requester_id;
        $ownerId = $swap->wantedBook->user_id;

        DB::transaction(function () use ($swap, $requesterId, $ownerId) {
            // apmainām grāmatas
            Book::where('id', $swap->offered_book_id)->update(['user_id' => $ownerId, 'status' => 'Available']);
            Book::where('id', $swap->wanted_book_id)->update(['user_id' => $requesterId, 'status' => 'Available']);
            $swap->update(['status' => 'accepted']);

            // noraida citus pieprasījumus uz tām pašām grāmatām
            $conflicting = SwapRequest::where('id', '!=', $swap->id)
                ->where('status', 'pending')
                ->where(function ($q) use ($swap) {
                    $q->whereIn('offered_book_id', [$swap->offered_book_id, $swap->wanted_book_id])
                      ->orWhereIn('wanted_book_id', [$swap->offered_book_id, $swap->wanted_book_id]);
                })
                ->get(['id', 'offered_book_id', 'wanted_book_id']);

            if ($conflicting->isNotEmpty()) {
                $bookIdsToFree = $conflicting
                    ->flatMap(fn($s) => [$s->offered_book_id, $s->wanted_book_id])
                    ->unique()
                    ->diff([$swap->offered_book_id, $swap->wanted_book_id])
                    ->values();

                SwapRequest::whereIn('id', $conflicting->pluck('id'))->update(['status' => 'declined']);

                if ($bookIdsToFree->isNotEmpty()) {
                    Book::whereIn('id', $bookIdsToFree)->update(['status' => 'Available']);
                }
            }
        });

        try {
            User::find($swap->requester_id)->notify(new SwapAccepted($swap));
        } catch (\Exception $e) {}

        return response()->json($swap->fresh(['offeredBook', 'wantedBook', 'requester:id,name']));
    }

    public function decline(Request $request, SwapRequest $swap)
    {
        if ($swap->wantedBook->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

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

        return response()->json($swap->fresh(['offeredBook', 'wantedBook', 'requester:id,name']));
    }
}
