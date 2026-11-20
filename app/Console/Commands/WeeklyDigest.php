<?php

namespace App\Console\Commands;

use App\Mail\WeeklyDigest as WeeklyDigestMail;
use App\Models\User;
use App\Support\Digest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

// sūta iknedēļas apkopojumu tiem, kas to ieslēguši iestatījumos
// scheduler to palaiž pirmdienās (routes/console.php)
class WeeklyDigest extends Command
{
    protected $signature = 'bookloop:weekly-digest
        {--user= : sūtīt tikai vienam lietotājam (e-pasts)}
        {--force : sūtīt arī ja šonedēļ jau sūtīts}';

    protected $description = 'Nosūta iknedēļas grāmatu apkopojumu';

    public function handle(): int
    {
        // kopējie saraksti visiem vienādi, rēķinām vienreiz
        $global = Digest::global();
        $sent = 0;
        $skipped = 0;

        $users = User::where('digest_opt_in', true)
            ->where('is_blocked', false)
            ->whereNull('deleted_at')
            ->when($this->option('user'), fn($q, $email) => $q->where('email', $email))
            ->when(!$this->option('force'), fn($q) => $q->where(function ($q) {
                // lai nesūta divreiz, ja komandu palaiž vēlreiz
                $q->whereNull('digest_sent_at')->orWhere('digest_sent_at', '<', now()->subDays(6));
            }));

        $users->chunkById(100, function ($chunk) use ($global, &$sent, &$skipped) {
            foreach ($chunk as $user) {
                $nearby = Digest::nearby($user);

                if (Digest::isEmpty($global, $nearby)) {
                    $skipped++;
                    continue;
                }

                try {
                    Mail::to($user)->send(new WeeklyDigestMail($user, $global, $nearby));
                    $user->forceFill(['digest_sent_at' => now()])->save();
                    $sent++;
                } catch (\Throwable $e) {
                    // viens slikts e-pasts neaptur pārējos
                    report($e);
                    $this->warn("Neizdevās: {$user->email}");
                }
            }
        });

        $this->info("Nosūtīti: $sent, izlaisti (nav ko rādīt): $skipped");

        return self::SUCCESS;
    }
}
