<?php

namespace App\Providers;

use App\Mail\SplitTransport;
use App\Support\ServerBoot;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
        // MAIL_MAILER=split - daļa e-pastu pa īstam (gmail), pārējie uz mailtrap
        // transportus izveido tikai kad vajag, lai bez gmail paroles nekas nelūzt
        Mail::extend('split', function (array $config) {
            $manager = app('mail.manager');

            return new SplitTransport(
                fn() => $manager->mailer($config['sandbox'])->getSymfonyTransport(),
                fn() => $manager->mailer($config['real'])->getSymfonyTransport(),
                $config['recipients'] ?? [],
                filled(config('mail.mailers.real.username')) && filled(config('mail.mailers.real.password')),
            );
        });

        // e2e testos vienmēr nobūvētie faili, arī ja strādā npm run dev
        if (env('E2E_TESTS')) {
            Vite::useHotFile(storage_path('framework/e2e-no-hot'));
        }

        // https tikai, ja lapa tiešām ir uz https (docker strādā uz http://localhost)
        if (config('app.env') === 'production' && str_starts_with(config('app.url'), 'https://')) {
            \URL::forceScheme('https');
        }
        
        // SQLite FK validācija pēc noklusējuma ir izslēgta
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        // "php artisan serve" palaišana - visi lietotāji izlogoti
        // (docker to dara entrypoint.sh ar php artisan auth:logout-all)
        if ($this->app->runningInConsole() && ($_SERVER['argv'][1] ?? null) === 'serve') {
            try {
                ServerBoot::start();
            } catch (\Throwable $e) {
                // ja vēl nav datubāzes/tabulas, neko nedara
            }
        }

        // paroles reset links jāved uz Vue, nevis Blade lapu
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return url('/reset-password?token=' . $token . '&email=' . urlencode($user->email));
        });
    }
}
