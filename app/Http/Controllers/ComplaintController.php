<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ComplaintMessage;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    // list: user sees own, admin sees all
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Complaint::with(['user:id,name', 'latestMessage.sender:id,name,is_admin'])
            ->latest();

        if (!$user->is_admin) {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->get()->map(fn($c) => [
            'id'          => $c->id,
            'subject'     => $c->subject,
            'status'      => $c->status,
            'user'        => $c->user,
            'last_msg'    => $c->latestMessage->first()?->body,
            'updated_at'  => $c->updated_at,
        ]));
    }

    // create complaint + first message
    public function store(Request $request)
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body'    => ['required', 'string', 'max:2000'],
        ]);

        $complaint = Complaint::create([
            'user_id' => $request->user()->id,
            'subject' => $request->subject,
            'status'  => 'open',
        ]);

        ComplaintMessage::create([
            'complaint_id' => $complaint->id,
            'sender_id'    => $request->user()->id,
            'body'         => $request->body,
        ]);

        return response()->json($this->formatComplaint($complaint), 201);
    }

    // get complaint with all messages
    public function show(Request $request, Complaint $complaint)
    {
        $user = $request->user();

        if (!$user->is_admin && $complaint->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $complaint->load(['user:id,name', 'messages.sender:id,name,is_admin']);

        return response()->json($this->formatComplaint($complaint));
    }

    // send a message to an existing complaint
    public function addMessage(Request $request, Complaint $complaint)
    {
        $user = $request->user();

        if (!$user->is_admin && $complaint->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($complaint->status === 'closed' && !$user->is_admin) {
            return response()->json(['message' => 'This complaint is closed.'], 422);
        }

        $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $msg = ComplaintMessage::create([
            'complaint_id' => $complaint->id,
            'sender_id'    => $user->id,
            'body'         => $request->body,
        ]);

        // re-open if closed and admin replies
        if ($complaint->status === 'closed' && $user->is_admin) {
            $complaint->update(['status' => 'open', 'updated_at' => now()]);
        } else {
            $complaint->touch();
        }

        $msg->load('sender:id,name,is_admin');

        return response()->json([
            'id'         => $msg->id,
            'sender_id'  => $msg->sender_id,
            'sender'     => $msg->sender,
            'body'       => $msg->body,
            'created_at' => $msg->created_at,
        ], 201);
    }

    // admin closes a complaint
    public function close(Request $request, Complaint $complaint)
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $complaint->update(['status' => 'closed']);
        return response()->json(['status' => 'closed']);
    }

    private function formatComplaint(Complaint $complaint): array
    {
        return [
            'id'       => $complaint->id,
            'subject'  => $complaint->subject,
            'status'   => $complaint->status,
            'user'     => $complaint->user,
            'messages' => $complaint->messages?->map(fn($m) => [
                'id'         => $m->id,
                'sender_id'  => $m->sender_id,
                'sender'     => $m->sender,
                'body'       => $m->body,
                'created_at' => $m->created_at,
            ]),
            'updated_at' => $complaint->updated_at,
        ];
    }
}
