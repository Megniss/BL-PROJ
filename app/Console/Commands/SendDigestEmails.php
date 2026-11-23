<?php

namespace App\Console\Commands;

use App\Mail\DigestEmail;
use App\Models\User;
use App\Support\Digest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

// sūta e-pastus tiem, kas tos ieslēguši iestatījumos
// katrs veids ir atsevišķs e-pasts, scheduler tos sūta dažādās dienās (routes/console.php)
class SendDigestEmails extends Command
{
    protected $signature = 'bookloop:emails
        {type? : nearby, popular, authors vai rated (ja nav, tad visi)}
        {--user= : sūtīt tikai vienam lietotājam (e-pasts)}
        {--force : sūtīt arī ja šonedēļ jau sūtīts}';

    protected $description = 'Nosūta grāmatu e-pastus (tuvumā, populārākās, autori, vērtējumi)';

    public function handle(): int
    {
        $type = $this->argument('type');
        if ($type && !in_array($type, Digest::TYPES)) {
            $this->error('Nav tāda veida. Var: ' . implode(', ', Digest::TYPES));

            return self::FAILURE;
        }

        // kopējie saraksti visiem vienādi, rēķinām vienreiz
        $global = Digest::global();

        foreach ($type ? [$type] : Digest::TYPES as $t) {
            $this->sendType($t, $global);
        }

        return self::SUCCESS;
    }

    private function sendType(string $type, array $global): void
    {
        $sent = 0;
        $skipped = 0;

        $users = User::where('is_blocked', false)
            ->whereNull('deleted_at')
            ->when($this->option('user'), fn($q, $email) => $q->where('email', $email))
            ->whereHas('emailSubscriptions', function ($q) use ($type) {
                $q->where('type', $type);
                // lai nesūta divreiz, ja komandu palaiž vēlreiz
                if (!$this->option('force')) {
                    $q->where(fn($q) => $q->whereNull('sent_at')->orWhere('sent_at', '<', now()->subDays(6)));
                }
            });

        $users->chunkById(100, function ($chunk) use ($type, $global, &$sent, &$skipped) {
            foreach ($chunk as $user) {
                $items = Digest::items($type, $user, $global);

                // tukšu e-pastu nesūtām
                if ($items === []) {
                    $skipped++;
                    continue;
                }

                try {
                    $this->send($user, new DigestEmail($user, $type, $items, $global['week']));
                    $user->emailSubscriptions()->where('type', $type)->update(['sent_at' => now()]);
                    $sent++;
                } catch (\Throwable $e) {
                    // viens slikts e-pasts neaptur pārējos
                    report($e);
                    $this->warn("Neizdevās: {$user->email}");
                }
            }
        });

        $this->info("$type - nosūtīti: $sent, izlaisti (nav ko rādīt): $skipped");
    }

    // mailtrap bezmaksas plāns neļauj vairākus e-pastus sekundē ("Too many emails per second")
    // tad pagaidām un mēģinām vēlreiz, max 3 reizes
    private function send(User $user, DigestEmail $mail): void
    {
        for ($try = 1; ; $try++) {
            try {
                Mail::to($user)->send($mail);

                return;
            } catch (\Throwable $e) {
                if ($try >= 3 || !str_contains($e->getMessage(), 'Too many emails')) {
                    throw $e;
                }
                $wait = $try * 5;
                $this->line("Mailtrap saka par ātru, gaidu {$wait}s...");
                sleep($wait);
            }
        }
    }
}
