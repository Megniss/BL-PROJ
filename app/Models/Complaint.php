<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'subject', 'category', 'status', 'admin_read_at'])]
class Complaint extends Model
{
    // iemesli, ko izvēlas no saraksta. tulkojumi frontendā (support.cat.*)
    // angliskais nosaukums saglabājas kā temats (e-pastiem un vecajiem paziņojumiem)
    public const CATEGORIES = [
        'account' => 'Account or login problem',
        'book' => 'Problem with my book',
        'swap' => 'Problem with a swap',
        'not_received' => 'Did not receive a book',
        'user' => 'Report a user',
        'content' => 'Inappropriate content',
        'bug' => 'Website error',
        'suggestion' => 'Suggestion or idea',
        'other' => 'Other',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ComplaintMessage::class)->orderBy('created_at');
    }

    // pēdējā ziņa priekšskatījumam sarakstā
    public function latestMessage(): HasMany
    {
        return $this->hasMany(ComplaintMessage::class)->latest('created_at')->limit(1);
    }
}
