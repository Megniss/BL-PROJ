<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\SwapRequest;
use App\Models\User;
use App\Support\LatvianCities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// statistikas tabs - nedēļas, žanri un pilsētas
class AdminStatsTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('auth_token');
        $token->accessToken->forceFill(['admin_until' => now()->addHour()])->save();

        return $token->plainTextToken;
    }

    public function test_stats_need_admin(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/admin/stats')->assertForbidden();
    }

    public function test_stats_count_weeks_genres_and_cities(): void
    {
        // 2 rīdzinieki, 1 liepājnieks, 1 bez vietas, 1 vecs lietotājs (pirms 12 nedēļām)
        $anna = User::factory()->create(['latitude' => 56.96, 'longitude' => 24.12]);
        $janis = User::factory()->create(['latitude' => 56.94, 'longitude' => 24.10]);
        User::factory()->create(['latitude' => 56.50, 'longitude' => 21.02]);
        User::factory()->create();
        User::factory()->create(['created_at' => now()->subWeeks(20)]);

        Book::factory()->count(3)->create(['user_id' => $anna->id, 'genre' => 'Fantāzija']);
        Book::factory()->create(['user_id' => $janis->id, 'genre' => 'Romāns']);

        $swap = SwapRequest::create([
            'requester_id' => $anna->id,
            'owner_id' => $janis->id,
            'offered_book_id' => Book::factory()->create(['user_id' => $anna->id, 'genre' => 'Romāns'])->id,
            'wanted_book_id' => Book::factory()->create(['user_id' => $janis->id, 'genre' => 'Romāns'])->id,
            'status' => 'pending',
        ]);
        $this->actingAs($janis)->patchJson("/api/swap-requests/{$swap->id}/accept")->assertOk();
        $this->app['auth']->forgetGuards();

        $data = $this->withToken($this->adminToken())->getJson('/api/admin/stats')->assertOk()->json();

        $this->assertCount(12, $data['weeks']);
        $thisWeek = end($data['weeks']);
        // 4 jauni + admins (vecais nav šajā nedēļā)
        $this->assertSame(5, $thisWeek['users']);
        $this->assertSame(1, $thisWeek['requests']);
        $this->assertSame(1, $thisWeek['accepted']);
        $this->assertSame(1, $data['totals']['swaps_accepted']);

        $genres = collect($data['genres'])->pluck('count', 'name');
        $this->assertSame(3, $genres['Fantāzija']);
        $this->assertSame(3, $genres['Romāns']);

        $cities = collect($data['cities'])->pluck('count', 'name');
        $this->assertSame(2, $cities['Rīga']);
        $this->assertSame(1, $cities['Liepāja']);
        // admins, bez vietas lietotājs un vecais lietotājs
        $this->assertSame(3, $data['no_location']);
    }

    public function test_nearest_city(): void
    {
        $this->assertSame('Rīga', LatvianCities::nearest(56.95, 24.10));
        $this->assertSame('Daugavpils', LatvianCities::nearest(55.88, 26.52));
        // vidū starp pilsētām (tālāk par 25 km)
        $this->assertNull(LatvianCities::nearest(57.80, 23.00));
        $this->assertNull(LatvianCities::nearest(null, null));
    }
}
