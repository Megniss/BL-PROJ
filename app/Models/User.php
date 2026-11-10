<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

#[Fillable(['name', 'email', 'password', 'show_joined', 'show_swaps', 'show_swap_history', 'show_on_map', 'is_admin', 'is_blocked'])]
#[Hidden(['password', 'remember_token', 'admin_password', 'latitude', 'longitude'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function blocking(): HasMany
    {
        return $this->hasMany(Block::class, 'blocker_id');
    }

    public function blockedBy(): HasMany
    {
        return $this->hasMany(Block::class, 'blocked_id');
    }

    // visi bloķētie lietotāji (abos virzienos)
    public function blockedUserIds(): Collection
    {
        $out = $this->blocking()->pluck('blocked_id');
        $in = $this->blockedBy()->pluck('blocker_id');
        return $out->merge($in)->unique()->values();
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    // vai ar pašreizējo tokenu ir ievadīta admin parole
    public function adminUnlocked(): bool
    {
        $token = $this->currentAccessToken();

        return $this->is_admin
            && $token instanceof PersonalAccessToken
            && $token->admin_until
            && now()->lt($token->admin_until);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'show_joined' => 'boolean',
            'show_swaps' => 'boolean',
            'show_swap_history' => 'boolean',
            'is_admin' => 'boolean',
            'admin_password' => 'hashed',
            'admin_password_change' => 'boolean',
            'is_blocked' => 'boolean',
            'deleted_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_prompted' => 'boolean',
            'show_on_map' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
