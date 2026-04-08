<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['from_user_id', 'to_user_id', 'body', 'read_at', 'edited_at'];

    protected $casts = [
        'read_at'   => 'datetime',
        'edited_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
