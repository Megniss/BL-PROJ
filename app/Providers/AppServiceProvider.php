<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // SQLite FK validācija pēc noklusējuma ir izslēgta
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        // paroles reset links jāved uz Vue, nevis Blade lapu
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return url('/reset-password?token=' . $token . '&email=' . urlencode($user->email));
        });
    }
}
