<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// liels datu apjoms veiktspējas testiem (NFP-01, NFP-02)
// php artisan db:seed --class=PerformanceSeeder
// lietotāji perf1..perf200@bookloop.test, parole Password1!
class PerformanceSeeder extends Seeder
{
    public int $users = 200;
    public int $books = 10000;
    public int $swaps = 500;

    public function run(): void
    {
        if (DB::table('users')->where('email', 'perf1@bookloop.test')->exists()) {
            $this->command?->info('perf dati jau ir, neko nepievieno');
            return;
        }

        $now = now();
        $password = Hash::make('Password1!');

        // lietotāji pa visu latviju, vieta noapaļota kā īstajiem
        $rows = [];
        for ($i = 1; $i <= $this->users; $i++) {
            $rows[] = [
                'name' => "Lasītājs $i",
                'email' => "perf$i@bookloop.test",
                'password' => $password,
                'latitude' => round(mt_rand(5570, 5790) / 100, 2),
                'longitude' => round(mt_rand(2100, 2800) / 100, 2),
                'two_factor_prompted' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->insert('users', $rows);

        $userIds = DB::table('users')->where('email', 'like', 'perf%@bookloop.test')->pluck('id')->all();

        // nosaukumi no vārdu kombinācijām, lai vienai grāmatai ir vairāki eksemplāri
        $first = ['Mazais', 'Lielais', 'Zaļais', 'Klusais', 'Vecais', 'Jaunais', 'Pēdējais', 'Baltais', 'Melnais', 'Sarkanais',
            'Tālais', 'Slepenais', 'Aizmirstais', 'Zelta', 'Sudraba', 'Ziemeļu', 'Rīgas', 'Jūras', 'Meža', 'Nakts'];
        $second = ['Princis', 'Ceļš', 'Dārzs', 'Kalns', 'Ezers', 'Pils', 'Vējš', 'Sapnis', 'Noslēpums', 'Stāsts',
            'Ceļotājs', 'Laiks', 'Upe', 'Zvaigzne', 'Grāmata', 'Ugunskurs', 'Tilts', 'Logs', 'Mājas', 'Kuģis',
            'Ozols', 'Dziesma', 'Karte', 'Atslēga', 'Vēstule'];
        $authors = ['RudolfsBlaumanis', 'VizmaBelsevica', 'Tolkien', 'JkRowling', 'FrankHerbert', 'AnnaBrigadere', 'Rainis',
            'Aspazija', 'RegīnaEzera', 'NoraIkstena', 'AgateChristie', 'StephenKing', 'GeorgeOrwell', 'LevTolstojs', 'Cehovs'];
        $genres = ['Fantāzija', 'Detektīvs', 'Trillenis', 'Romantika', 'Piedzīvojumi', 'Romāns', 'Bērnu grāmatas', 'Dzeja'];
        $languages = ['Latviešu', 'English', 'Русский'];
        $conditions = ['New', 'Good', 'Fair', 'Worn'];

        $rows = [];
        for ($i = 0; $i < $this->books; $i++) {
            $a = mt_rand(0, count($first) - 1);
            $b = mt_rand(0, count($second) - 1);
            $created = $now->copy()->subMinutes(mt_rand(0, 60 * 24 * 365));
            $rows[] = [
                'user_id' => $userIds[array_rand($userIds)],
                'title' => $first[$a] . $second[$b],
                // autors atkarīgs no nosaukuma, lai sakrīt grupas
                'author' => $authors[($a * 7 + $b) % count($authors)],
                'genre' => $genres[($a + $b) % count($genres)],
                'language' => $languages[mt_rand(0, 2)],
                'condition' => $conditions[mt_rand(0, 3)],
                'status' => 'Available',
                'created_at' => $created,
                'updated_at' => $created,
            ];
        }
        $this->insert('books', $rows);

        // pieņemtas apmaiņas ar vērtējumiem, lai kārtošana pēc vērtējuma ko dara
        $bookIds = DB::table('books')->whereIn('user_id', $userIds)->pluck('user_id', 'id')->all();
        $ids = array_keys($bookIds);

        for ($i = 0; $i < $this->swaps; $i++) {
            $wanted = $ids[array_rand($ids)];
            $offered = $ids[array_rand($ids)];
            if ($wanted === $offered || $bookIds[$wanted] === $bookIds[$offered]) {
                continue;
            }

            $swapId = DB::table('swap_requests')->insertGetId([
                'requester_id' => $bookIds[$offered],
                'owner_id' => $bookIds[$wanted],
                'offered_book_id' => $offered,
                'wanted_book_id' => $wanted,
                'status' => 'accepted',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('ratings')->insert([
                'swap_request_id' => $swapId,
                'book_id' => $wanted,
                'rater_id' => $bookIds[$offered],
                'stars' => mt_rand(1, 5),
                'review' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command?->info("pievienoti {$this->users} lietotāji un {$this->books} grāmatas");
    }

    // pa 500 rindām, citādi sqlite pārsniedz mainīgo limitu
    private function insert(string $table, array $rows): void
    {
        DB::transaction(function () use ($table, $rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        });
    }
}
