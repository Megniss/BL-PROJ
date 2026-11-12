<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\Complaint;
use App\Models\ComplaintMessage;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    // lietotāja puse - tikai savas sūdzības

    public function index(Request $request)
    {
        $complaints = Complaint::with('latestMessage')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($complaints->map(fn($c) => $this->formatListItem($c)));
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $complaint = Complaint::create([
            'user_id' => $request->user()->id,
            'subject' => $request->subject,
            'status' => 'open',
        ]);

        ComplaintMessage::create([
            'complaint_id' => $complaint->id,
            'sender_id' => $request->user()->id,
            'body' => $request->body,
        ]);

        $complaint->load(['user:id,name', 'messages.sender:id,name']);

        return response()->json($this->formatComplaint($complaint), 201);
    }

    public function show(Request $request, Complaint $complaint)
    {
        if ($complaint->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $complaint->load(['user:id,name', 'messages.sender:id,name']);

        return response()->json($this->formatComplaint($complaint));
    }

    public function addMessage(Request $request, Complaint $complaint)
    {
        if ($complaint->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($complaint->status === 'closed') {
            return response()->json(['message' => 'This complaint is closed.'], 422);
        }

        $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $msg = $complaint->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $request->body,
        ]);
        $complaint->touch();

        return response()->json($this->formatMessage($msg, $complaint), 201);
    }

    // admin puse

    public function adminIndex()
    {
        $complaints = Complaint::with(['user:id,name', 'latestMessage'])->latest()->get();

        return response()->json($complaints->map(fn($c) => $this->formatListItem($c)));
    }

    public function adminShow(Complaint $complaint)
    {
        $complaint->load(['user:id,name', 'messages.sender:id,name']);

        return response()->json($this->formatComplaint($complaint));
    }

    public function adminReply(Request $request, Complaint $complaint)
    {
        $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $msg = $complaint->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $request->body,
        ]);

        // atbilde slēgtai sūdzībai to atver atpakaļ
        if ($complaint->status === 'closed') {
            $complaint->update(['status' => 'open']);
        } else {
            $complaint->touch();
        }

        $this->log($request, 'support_reply', $complaint, $request->body);

        return response()->json($this->formatMessage($msg, $complaint), 201);
    }

    public function close(Request $request, Complaint $complaint)
    {
        $complaint->update(['status' => 'closed']);
        $this->log($request, 'close_complaint', $complaint);

        return response()->json(['status' => 'closed']);
    }

    private function log(Request $request, string $action, Complaint $complaint, ?string $reason = null): void
    {
        $complaint->loadMissing('user:id,name');

        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => $action,
            'target_type' => 'complaint',
            'target_id' => $complaint->id,
            'target_name' => $complaint->user->name . ' - ' . $complaint->subject,
            'reason' => $reason,
        ]);
    }

    private function formatListItem(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'subject' => $complaint->subject,
            'status' => $complaint->status,
            'user' => $complaint->relationLoaded('user') ? $complaint->user : null,
            'last_msg' => $complaint->latestMessage->first()?->body,
            'updated_at' => $complaint->updated_at,
        ];
    }

    private function formatComplaint(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'subject' => $complaint->subject,
            'status' => $complaint->status,
            'user' => $complaint->user,
            'messages' => $complaint->messages->map(fn($m) => $this->formatMessage($m, $complaint)),
            'updated_at' => $complaint->updated_at,
        ];
    }

    // admina ziņa ir jebkura, ko nav rakstījis sūdzības autors
    private function formatMessage(ComplaintMessage $msg, Complaint $complaint): array
    {
        $msg->loadMissing('sender:id,name');

        return [
            'id' => $msg->id,
            'sender_id' => $msg->sender_id,
            'sender' => $msg->sender,
            'from_admin' => $msg->sender_id !== $complaint->user_id,
            'body' => $msg->body,
            'created_at' => $msg->created_at,
        ];
    }
}
