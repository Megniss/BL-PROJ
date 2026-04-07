<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportSeeds extends Command
{
    protected $signature = 'db:seed-export';
    protected $description = 'Export current database data into DatabaseSeeder.php';

    public function handle(): void
    {
        $users = DB::table('users')->get()->toArray();
        $books = DB::table('books')->get()->toArray();

        $usersPhp = $this->arrayToPhp($users);
        $booksPhp = $this->arrayToPhp($books);

        $stub = <<<PHP
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- USERS ---
        \$users = $usersPhp;

        DB::table('users')->insert(\$users);

        // --- BOOKS ---
        \$books = $booksPhp;

        DB::table('books')->insert(\$books);
    }
}
PHP;

        file_put_contents(database_path('seeders/DatabaseSeeder.php'), $stub);

        $this->info('DatabaseSeeder.php updated with ' . count($users) . ' users and ' . count($books) . ' books.');
    }

    private function arrayToPhp(array $rows): string
    {
        $lines = ['['];
        foreach ($rows as $row) {
            $lines[] = '            [';
            foreach ((array) $row as $key => $value) {
                $exportedValue = is_null($value) ? 'null' : var_export($value, true);
                $lines[] = "                '$key' => $exportedValue,";
            }
            $lines[] = '            ],';
        }
        $lines[] = '        ]';
        return implode("\n", $lines);
    }
}
