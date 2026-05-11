<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    // saraksts ar visām sarunām
    public function index(Request $request)
    {
        $me = $request->user()->id;

        $latestIds = DB::table('messages')
            ->selectRaw('MAX(id) as id')
            ->where(function ($q) use ($me) {
                $q->where('from_user_id', $me)->orWhere('to_user_id', $me);
            })
            ->groupByRaw('CASE WHEN from_user_id = ? THEN to_user_id ELSE from_user_id END', [$me])
            ->pluck('id');

        $lastMessages = Message::whereIn('id', $latestIds)->latest()->get();

        $unreadCounts = Message::where('to_user_id', $me)
            ->whereNull('read_at')
            ->groupBy('from_user_id')
            ->selectRaw('from_user_id, COUNT(*) as cnt')
            ->pluck('cnt', 'from_user_id');

        $partnerIds = $lastMessages->map(
            fn($m) => $m->from_user_id === $me ? $m->to_user_id : $m->from_user_id
        )->unique()->values();

        $partners = User::whereIn('id', $partnerIds)->get()->keyBy('id');

        // vienā query visus bloķētos
        $blockedIds = $request->user()->blockedUserIds();

        $conversations = $lastMessages->map(function ($msg) use ($me, $partners, $unreadCounts, $blockedIds) {
            $partnerId = $msg->from_user_id === $me ? $msg->to_user_id : $msg->from_user_id;
            $partner = $partners->get($partnerId);
            if (!$partner) return null;

            return [
                'user' => ['id' => $partner->id, 'name' => $partner->name],
                'last_message' => ['body' => $msg->body, 'created_at' => $msg->created_at],
                'unread' => $unreadCounts->get($partnerId, 0),
                'is_blocked' => $blockedIds->contains($partnerId),
            ];
        })->filter()->values();

        return response()->json($conversations);
    }

    // konkrēta saruna ar lietotāju
    public function show(Request $request, User $user)
    {
        $me = $request->user()->id;

        $messages = Message::where(function ($q) use ($me, $user) {
            $q->where('from_user_id', $me)->where('to_user_id', $user->id);
        })->orWhere(function ($q) use ($me, $user) {
            $q->where('from_user_id', $user->id)->where('to_user_id', $me);
        })->with('sender:id,name')->oldest()->get();

        // atzīmē kā izlasītas
        Message::where('from_user_id', $user->id)
            ->where('to_user_id', $me)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        if ((int) $data['to_user_id'] === $request->user()->id) {
            return response()->json(['message' => 'You cannot message yourself.'], 422);
        }

        // bloķētie nevar rakstīt
        $blocked = $request->user()->blockedUserIds()->contains((int) $data['to_user_id']);
        if ($blocked) {
            return response()->json(['message' => 'You cannot message this user.'], 422);
        }

        $sender = $request->user();
        $recipient = User::find($data['to_user_id']);

        // nesūta notifikāciju ja jau ir nelasīta
        $hasUnread = Message::where('from_user_id', $sender->id)
            ->where('to_user_id', $recipient->id)
            ->whereNull('read_at')
            ->exists();

        $message = Message::create([
            'from_user_id' => $sender->id,
            'to_user_id' => $recipient->id,
            'body' => $data['body'],
        ]);

        if (!$hasUnread) {
            try {
                $recipient->notify(new NewMessage($sender, $data['body']));
            } catch (\Exception $e) {}
        }

        return response()->json($message->load('sender:id,name'), 201);
    }

    // rediģēt ziņu — tikai sūtītājs, tikai ja nav izlasīta
    public function update(Request $request, Message $message)
    {
        if ($message->from_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($message->read_at !== null) {
            return response()->json(['message' => 'Cannot edit a read message.'], 422);
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $message->update([
            'body' => $data['body'],
            'edited_at' => now(),
        ]);

        return response()->json($message->load('sender:id,name'));
    }

    // dzēst ziņu — tikai sūtītājs, tikai ja nav izlasīta
    public function destroy(Request $request, Message $message)
    {
        if ($message->from_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($message->read_at !== null) {
            return response()->json(['message' => 'Cannot unsend a read message.'], 422);
        }

        $senderId = $message->from_user_id;
        $recipientId = $message->to_user_id;

        $message->delete();

        // dzēš notifikāciju ja vairs nav nelasītu ziņu
        $hasMoreUnread = Message::where('from_user_id', $senderId)
            ->where('to_user_id', $recipientId)
            ->whereNull('read_at')
            ->exists();

        if (!$hasMoreUnread) {
            $recipient = \App\Models\User::find($recipientId);
            $recipient?->notifications()
                ->whereNull('read_at')
                ->where('type', \App\Notifications\NewMessage::class)
                ->where('data->sender_id', $senderId)
                ->delete();
        }

        return response()->json(null, 204);
    }

    public function unreadCount(Request $request)
    {
        $count = Message::where('to_user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }
}
