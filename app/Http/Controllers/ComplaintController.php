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
        // admins atbild admin panelī, pats sev pieteikumu nerakstīs
        if ($request->user()->is_admin) {
            return response()->json(['message' => 'Admins use the admin panel for support.'], 403);
        }

        $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        // admin_read_at paliek null - adminam rādās kā jauns
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

        if ($request->user()->is_admin) {
            return response()->json(['message' => 'Admins use the admin panel for support.'], 403);
        }

        $msg = $this->createMessage($request, $complaint);

        // jauna lietotāja ziņa - adminam atkal nelasīts
        $complaint->update(['admin_read_at' => null]);
        $complaint->touch();

        return response()->json($this->formatMessage($msg, $complaint), 201);
    }

    // admin puse

    public function adminIndex()
    {
        $complaints = Complaint::with(['user:id,name', 'latestMessage'])->latest('updated_at')->get();

        return response()->json($complaints->map(fn($c) => $this->formatListItem($c)));
    }

    // admin panelim - cik pieteikumos ir neizlasītas lietotāju ziņas
    public function adminUnreadCount()
    {
        return response()->json(['count' => Complaint::whereNull('admin_read_at')->count()]);
    }

    public function adminShow(Complaint $complaint)
    {
        // atverot skaitās izlasīts, updated_at nemaina
        if (!$complaint->admin_read_at) {
            $complaint->timestamps = false;
            $complaint->update(['admin_read_at' => now()]);
            $complaint->timestamps = true;
        }

        $complaint->load(['user:id,name', 'messages.sender:id,name']);

        return response()->json($this->formatComplaint($complaint));
    }

    public function adminReply(Request $request, Complaint $complaint)
    {
        $msg = $this->createMessage($request, $complaint);

        // atbilde slēgtai sūdzībai to atver atpakaļ
        if ($complaint->status === 'closed') {
            $complaint->update(['status' => 'open']);
        } else {
            $complaint->touch();
        }

        $this->log($request, 'support_reply', $complaint, $request->body ?: '[bilde]');

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

    // teksts un/vai bilde, tādi paši ierobežojumi kā parastajām ziņām
    private function createMessage(Request $request, Complaint $complaint): ComplaintMessage
    {
        $request->validate([
            'body' => ['nullable', 'string', 'max:2000', 'required_without:image'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        return $complaint->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $request->body ?? '',
            'image_path' => $request->file('image')?->store('support', 'local'),
        ]);
    }

    private function formatListItem(Complaint $complaint): array
    {
        $last = $complaint->latestMessage->first();

        return [
            'id' => $complaint->id,
            'subject' => $complaint->subject,
            'status' => $complaint->status,
            'user' => $complaint->relationLoaded('user') ? $complaint->user : null,
            'last_msg' => $last?->body,
            'last_image' => (bool) ($last?->image_path || $last?->image_removed),
            'unread' => $complaint->admin_read_at === null,
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
            'image_url' => $msg->image_url,
            'image_removed' => $msg->image_removed,
            'created_at' => $msg->created_at,
        ];
    }
}
