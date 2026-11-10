<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // e2e testos vienmēr nobūvētie faili, arī ja strādā npm run dev
        if (env('E2E_TESTS')) {
            Vite::useHotFile(storage_path('framework/e2e-no-hot'));
        }

        if (config('app.env') === 'production') {
            \URL::forceScheme('https');
        }
        
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
