<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

#[Fillable(['complaint_id', 'sender_id', 'body', 'image_path', 'image_removed'])]
class ComplaintMessage extends Model
{
    // tikai created_at, laravel to aizpilda pats (agrāk jaunai ziņai laiks bija tukšs)
    const UPDATED_AT = null;

    protected $casts = ['created_at' => 'datetime', 'image_removed' => 'boolean'];

    protected $hidden = ['image_path'];

    // īslaicīga saite uz bildi, tāpat kā Message
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
        return $this->belongsTo(User::class, 'sender_id');
    }
}
