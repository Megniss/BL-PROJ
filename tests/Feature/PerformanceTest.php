<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PerformanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// NFP-02: meklēšana ne ilgāk par 3 s katalogā ar 10 000 grāmatām
class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_SECONDS = 3.0;

    public function test_search_with_ten_thousand_books_is_fast_enough(): void
    {
        $this->seed(PerformanceSeeder::class);
        $this->assertSame(10000, \App\Models\Book::count());

        Sanctum::actingAs(User::where('email', 'perf1@bookloop.test')->first());

        $urls = [
            '/api/browse',
            '/api/browse?search=Princis',
            '/api/browse?search=Tolkien',
            '/api/browse?genres[]=Fantāzija&languages[]=Latviešu',
            '/api/browse?sort=nearest&max_km=50',
            '/api/browse?sort=top_rated',
            '/api/browse?sort=popular',
            '/api/browse?page=20',
            '/api/books/map',
        ];

        $times = [];
        foreach ($urls as $url) {
            $start = microtime(true);
            $res = $this->getJson($url);
            $sec = microtime(true) - $start;

            $res->assertOk();
            $times[$url] = $sec;
            $this->assertLessThan(self::MAX_SECONDS, $sec, "$url aizņēma " . round($sec, 2) . ' s');
        }

        // meklēšana tiešām kaut ko atrod
        $this->assertNotEmpty($this->getJson('/api/browse?search=Princis')->json('data'));

        // rezultāti redzami terminālī, der dokumentācijai
        fwrite(STDERR, "\n  NFP-02 meklēšanas laiki (10 000 grāmatas):\n");
        foreach ($times as $url => $sec) {
            fwrite(STDERR, sprintf("    %6d ms  %s\n", $sec * 1000, $url));
        }
    }
}
