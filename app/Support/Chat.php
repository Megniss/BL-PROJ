<?php

namespace App\Support;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// kopīgais abiem čatiem (lietotāju ziņas un atbalsts):
// ziņas validācija, bildes saglabāšana un "raksta..." statuss
class Chat
{
    // cik ilgi skaitās, ka raksta, ja nav jauna signāla
    const TYPING_SECONDS = 5;

    // teksts un/vai bilde, vienādi noteikumi abos čatos
    // $folder - "chat" vai "support", lai bildes var atšķirt
    public static function validateMessage(Request $request, string $folder, array $extraRules = []): array
    {
        $data = $request->validate($extraRules + [
            'body' => ['nullable', 'string', 'max:2000', 'required_without:image'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        unset($data['image']);
        $data['body'] = trim($data['body'] ?? '');
        $data['image_path'] = $request->file('image')?->store($folder, 'local');

        return $data;
    }

    // čata atslēga, piem. "user:5" vai "support:3"
    // atgriež [kešs kur es rakstu, kešs kur raksta otrs] vai null ja nav pieejas
    public static function typingKeys(User $me, string $chat): ?array
    {
        [$type, $id] = array_pad(explode(':', $chat, 2), 2, null);
        $id = (int) $id;

        if ($type === 'user' && $id && $id !== $me->id) {
            $other = User::find($id);
            if (!$other || $other->deleted_at || $me->blockedUserIds()->contains($id)) {
                return null;
            }

            // viena saruna abiem, tāpēc mazākais id pirmais
            $pair = min($me->id, $id) . '-' . max($me->id, $id);

            return ["typing:dm:$pair:{$me->id}", "typing:dm:$pair:$id"];
        }

        if ($type === 'support' && $id) {
            $complaint = Complaint::find($id);
            if (!$complaint) {
                return null;
            }

            // admins redz lietotāju, lietotājs redz jebkuru adminu
            if ($me->is_admin && $complaint->user_id !== $me->id) {
                return ["typing:support:$id:admin", "typing:support:$id:user"];
            }
            if ($complaint->user_id === $me->id) {
                return ["typing:support:$id:user", "typing:support:$id:admin"];
            }
        }

        return null;
    }

    public static function startTyping(User $me, string $chat): bool
    {
        $keys = self::typingKeys($me, $chat);
        if (!$keys) {
            return false;
        }

        Cache::put($keys[0], true, self::TYPING_SECONDS);

        return true;
    }

    // pēc nosūtīšanas uzreiz vairs neraksta
    public static function stopTyping(User $me, string $chat): void
    {
        $keys = self::typingKeys($me, $chat);
        if ($keys) {
            Cache::forget($keys[0]);
        }
    }

    public static function isOtherTyping(User $me, string $chat): bool
    {
        $keys = self::typingKeys($me, $chat);

        return $keys ? (bool) Cache::get($keys[1], false) : false;
    }
}
