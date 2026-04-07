<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['requester_id', 'owner_id', 'offered_book_id', 'wanted_book_id', 'status', 'requester_dismissed', 'owner_dismissed'])]
class SwapRequest extends Model
{
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function offeredBook(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'offered_book_id');
    }

    public function wantedBook(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'wanted_book_id');
    }
}
