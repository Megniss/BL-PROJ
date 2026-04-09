<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'subject', 'status'])]
class Complaint extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ComplaintMessage::class)->orderBy('created_at');
    }

    // just the last one, for the list view preview
    public function latestMessage(): HasMany
    {
        return $this->hasMany(ComplaintMessage::class)->latest('created_at')->limit(1);
    }
}
