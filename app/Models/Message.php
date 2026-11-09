<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class Message extends Model
{
    protected $fillable = ['from_user_id', 'to_user_id', 'body', 'image_path', 'image_removed', 'read_at', 'edited_at'];

    protected $hidden = ['image_path'];

    protected $appends = ['image_url'];

    protected $casts = [
        'read_at'   => 'datetime',
        'edited_at' => 'datetime',
        'image_removed' => 'boolean',
    ];

    // īslaicīga saite uz bildi, noapaļota līdz stundai, lai nemainās katrā pieprasījumā
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn() => $this->image_path
            ? URL::temporarySignedRoute(
                'storage.local',
                now()->addHours(2)->startOfHour(),
                ['path' => $this->image_path],
                absolute: false,
            )
            : null);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
