<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

// NFP-01: 100 vienlaicīgi lietotāji, 95% pieprasījumu <= 3 s
// vispirms: php artisan db:seed --class=PerformanceSeeder
// tad pret palaistu serveri: php artisan bookloop:load-test http://localhost:8000
class LoadTest extends Command
{
    protected $signature = 'bookloop:load-test {url=http://localhost:8000} {--users=100} {--rounds=5}';

    protected $description = 'Slodzes tests - vienlaicīgi pieprasījumi un 95. procentile';

    // parastās lapas, ko lietotājs atver
    private array $paths = [
        '/',
        '/browse',
        '/api/me',
        '/api/browse',
        '/api/browse?search=Princis',
        '/api/browse?sort=nearest&max_km=50',
        '/api/browse?genres[]=Fantāzija',
        '/api/books',
        '/api/messages/unread-count',
        '/api/notifications',
    ];

    public function handle(): int
    {
        $url = rtrim($this->argument('url'), '/');
        $users = (int) $this->option('users');
        $rounds = (int) $this->option('rounds');

        $people = User::where('email', 'like', 'perf%@bookloop.test')->limit($users)->get();
        if ($people->isEmpty()) {
            $this->error('nav perf lietotāju, palaid: php artisan db:seed --class=PerformanceSeeder');
            return self::FAILURE;
        }

        // tokeni uzreiz, lai login throttle netraucē
        $tokens = $people->map(fn($u) => $u->createToken('load-test')->plainTextToken)->all();

        $this->info("$users vienlaicīgi pieprasījumi x $rounds reizes pret $url");

        $times = [];
        $byPath = [];
        $errors = 0;

        for ($r = 1; $r <= $rounds; $r++) {
            $stats = [];

            $responses = Http::pool(function (Pool $pool) use ($users, $url, $tokens, &$stats) {
                $list = [];
                for ($i = 0; $i < $users; $i++) {
                    $path = $this->paths[$i % count($this->paths)];
                    $list[] = $pool->as((string) $i)
                        ->withToken($tokens[$i % count($tokens)])
                        ->acceptJson()
                        ->timeout(30)
                        ->withOptions(['on_stats' => function ($s) use ($i, &$stats) {
                            $stats[$i] = $s->getTransferTime();
                        }])
                        ->get($url . $path);
                }
                return $list;
            });

            foreach ($responses as $i => $res) {
                $path = $this->paths[$i % count($this->paths)];
                $ok = $res instanceof \Illuminate\Http\Client\Response && $res->successful();
                if (!$ok) {
                    $errors++;
                    continue;
                }
                $times[] = $stats[$i];
                $byPath[$path][] = $stats[$i];
            }

            $this->line("  $r. kārta gatava");
        }

        // tokenus aiz sevis notīra
        foreach ($people as $u) {
            $u->tokens()->where('name', 'load-test')->delete();
        }

        if (!$times) {
            $this->error('neviens pieprasījums neizdevās, vai serveris ir palaists?');
            return self::FAILURE;
        }

        $rows = [];
        foreach ($byPath as $path => $list) {
            $rows[] = [$path, count($list), $this->ms(array_sum($list) / count($list)), $this->ms($this->p95($list)), $this->ms(max($list))];
        }
        $this->table(['Lapa', 'Skaits', 'Vidēji', 'p95', 'Max'], $rows);

        $p95 = $this->p95($times);
        $total = count($times) + $errors;
        $this->line("Kopā: $total pieprasījumi, kļūdas: $errors");
        $this->line('Vidēji: ' . $this->ms(array_sum($times) / count($times)) . ', p95: ' . $this->ms($p95) . ', max: ' . $this->ms(max($times)));

        if ($p95 <= 3.0 && $errors === 0) {
            $this->info('NFP-01 izpildīts: 95% pieprasījumu <= 3 s');
            return self::SUCCESS;
        }

        $this->error('NFP-01 nav izpildīts');
        return self::FAILURE;
    }

    private function p95(array $list): float
    {
        sort($list);
        return $list[max(0, (int) ceil(count($list) * 0.95) - 1)];
    }

    private function ms(float $sec): string
    {
        return round($sec * 1000) . ' ms';
    }
}
