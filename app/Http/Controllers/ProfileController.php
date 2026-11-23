<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use App\Models\Block;
use App\Models\Book;
use App\Models\ComplaintMessage;
use App\Models\Message;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\SwapUnavailable;
use App\Support\Digest;
use App\Support\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Laravel\Sanctum\PersonalAccessToken;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $booksCount = $user->books()->count();
        $swapsCount = SwapRequest::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)->orWhere('owner_id', $user->id);
            })
            ->count();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'joined' => $user->created_at->toDateString(),
            'books' => $booksCount,
            'swaps' => $swapsCount,
            'show_joined' => $user->show_joined,
            'show_swaps' => $user->show_swaps,
            'show_swap_history' => $user->show_swap_history,
            'show_on_map' => $user->show_on_map,
            'emails' => $user->emailSettings(),
            'is_admin' => $user->is_admin,
            'is_only_admin' => $user->is_admin && !$this->hasOtherAdmins($user),
            'latitude' => $user->latitude,
            'longitude' => $user->longitude,
            'two_factor_enabled' => $user->two_factor_enabled,
        ]);
    }

    // atrašanās vieta tiek noapaļota līdz ~1 km
    public function updateLocation(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'latitude' => round($data['latitude'], 2),
            'longitude' => round($data['longitude'], 2),
        ])->save();

        return response()->json(['latitude' => $user->latitude, 'longitude' => $user->longitude]);
    }

    public function removeLocation(Request $request)
    {
        $request->user()->forceFill(['latitude' => null, 'longitude' => null])->save();

        return response()->json(['latitude' => null, 'longitude' => null]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'current_password' => ['nullable', 'string'],
            'new_password' => ['nullable', 'confirmed', Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
            'new_password_confirmation' => ['nullable', 'string'],
        ]);

        $oldEmail = $user->email;
        $emailChanged = mb_strtolower($data['email']) !== mb_strtolower($oldEmail);
        $passwordChanged = !empty($data['new_password']);

        // e-pastu un paroli var mainīt tikai ar pašreizējo paroli
        // citādi ar nozagtu tokenu var nomainīt e-pastu un tad paroli caur reset
        if ($emailChanged || $passwordChanged) {
            if (empty($data['current_password']) || !Hash::check($data['current_password'], $user->password)) {
                return response()->json([
                    'errors' => ['current_password' => ['Current password is incorrect.']],
                ], 422);
            }
        }

        if ($passwordChanged) {
            $user->password = Hash::make($data['new_password']);
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        if ($emailChanged || $passwordChanged) {
            // vecā e-pasta reset saite vairs neder
            DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
            // izlogo visas citas ierīces, šī paliek
            $this->logoutOtherDevices($request);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function updatePrivacy(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'show_joined' => ['required', 'boolean'],
            'show_swaps' => ['required', 'boolean'],
            'show_swap_history' => ['required', 'boolean'],
            'show_on_map' => ['sometimes', 'boolean'],
        ]);

        $user->show_joined = $data['show_joined'];
        $user->show_swaps = $data['show_swaps'];
        $user->show_swap_history = $data['show_swap_history'];
        // šo maina tikai ja atsūtīts, citādi paliek kā bija
        if (array_key_exists('show_on_map', $data)) {
            $user->show_on_map = $data['show_on_map'];
        }
        $user->save();
        $user->refresh();

        return response()->json([
            'show_joined' => $user->show_joined,
            'show_swaps' => $user->show_swaps,
            'show_swap_history' => $user->show_swap_history,
            'show_on_map' => $user->show_on_map,
        ]);
    }

    // e-pastu piekrišanas, katram veidam atsevišķi, piem. {"nearby": true, "rated": false}
    // pēc noklusējuma viss izslēgts (gdpr)
    public function updateEmails(Request $request)
    {
        $user = $request->user();
        $rules = [];
        foreach (Digest::TYPES as $type) {
            $rules[$type] = ['sometimes', 'boolean'];
        }
        $data = $request->validate($rules);

        foreach ($data as $type => $on) {
            if ($on) {
                $user->emailSubscriptions()->firstOrCreate(['type' => $type]);
            } else {
                $user->emailSubscriptions()->where('type', $type)->delete();
            }
        }

        return response()->json($user->emailSettings());
    }

    // konta dzēšana - lietotājs tiek anonimizēts, vēsture paliek
    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->is_admin && !$this->hasOtherAdmins($user)) {
            return response()->json(['message' => 'The only admin cannot delete their account.'], 403);
        }

        $request->validate([
            'password' => ['required', 'string'],
            'reason' => [$user->is_admin ? 'required' : 'nullable', 'string', 'max:500'],
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'errors' => ['password' => ['Password is incorrect.']],
            ], 422);
        }

        $pending = SwapRequest::where('status', 'pending')
            ->where(fn($q) => $q->where('requester_id', $user->id)->orWhere('owner_id', $user->id))
            ->with(['offeredBook', 'wantedBook'])
            ->get();

        $books = $user->books()->get();
        $oldEmail = $user->email;
        $chatImages = Message::where('from_user_id', $user->id)->whereNotNull('image_path')->pluck('image_path')
            ->merge(ComplaintMessage::where('sender_id', $user->id)->whereNotNull('image_path')->pluck('image_path'));

        DB::transaction(function () use ($user, $pending, $books, $oldEmail, $request) {
            // admina dzēšanu ieraksta žurnālā
            if ($user->is_admin) {
                AdminLog::create([
                    'admin_id' => $user->id,
                    'action' => 'delete_account',
                    'target_type' => 'user',
                    'target_id' => $user->id,
                    'target_name' => $user->name,
                    'reason' => $request->reason,
                ]);
            }

            // noraida gaidošās apmaiņas, otra grāmata atkal brīva
            foreach ($pending as $swap) {
                $otherBook = $swap->requester_id === $user->id ? $swap->wanted_book_id : $swap->offered_book_id;
                Book::where('id', $otherBook)->update(['status' => 'Available']);
                $swap->update(['status' => 'declined']);
            }

            // grāmatas paslēpj, lai vēsturē paliek nosaukums
            Book::where('user_id', $user->id)->update(['status' => 'Removed', 'cover_image' => null]);

            // sūtītās bildes dzēš, ziņā paliek atzīme
            Message::where('from_user_id', $user->id)
                ->whereNotNull('image_path')
                ->update(['image_path' => null, 'image_removed' => true]);
            ComplaintMessage::where('sender_id', $user->id)
                ->whereNotNull('image_path')
                ->update(['image_path' => null, 'image_removed' => true]);

            Block::where('blocker_id', $user->id)->orWhere('blocked_id', $user->id)->delete();
            $user->notifications()->delete();
            $user->emailSubscriptions()->delete();
            TwoFactor::forget($user);
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $user->forceFill([
                'name' => 'Deleted user',
                'email' => "deleted-{$user->id}@deleted.invalid",
                'password' => Hash::make(Str::random(40)),
                'remember_token' => null,
                'is_admin' => false,
                'admin_password' => null,
                'admin_password_change' => false,
                'show_joined' => false,
                'show_swaps' => false,
                'show_swap_history' => false,
                'latitude' => null,
                'longitude' => null,
                'two_factor_enabled' => false,
                'deleted_at' => now(),
            ])->save();
        });

        Storage::disk('local')->delete($chatImages->all());

        foreach ($books as $book) {
            if ($book->cover_image) {
                Storage::disk('public')->delete($book->cover_image);
            }
        }

        // paziņo otram lietotājam
        foreach ($pending as $swap) {
            $isRequester = $swap->requester_id === $user->id;
            $other = User::find($isRequester ? $swap->owner_id : $swap->requester_id);
            $title = $isRequester ? $swap->wantedBook?->title : $swap->offeredBook?->title;

            try {
                $other?->notify(new SwapUnavailable($title ?? ''));
            } catch (\Exception $e) {}
        }

        return response()->json(null, 204);
    }

    private function hasOtherAdmins(User $user): bool
    {
        return User::where('is_admin', true)
            ->whereNull('deleted_at')
            ->where('id', '!=', $user->id)
            ->exists();
    }

    public function history(Request $request)
    {
        $user = $request->user();

        $history = SwapRequest::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('requester_id', $user->id)->orWhere('owner_id', $user->id);
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

    // dzēš visus citus tokenus un atcerētās 2FA ierīces, pašreizējais paliek
    private function logoutOtherDevices(Request $request): void
    {
        $user = $request->user();
        $current = $user->currentAccessToken();

        $user->tokens()
            ->when($current instanceof PersonalAccessToken, fn($q) => $q->where('id', '!=', $current->id))
            ->delete();

        TwoFactor::forget($user);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'All marked as read.']);
    }
}
