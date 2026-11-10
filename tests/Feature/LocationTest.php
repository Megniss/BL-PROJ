<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    // pilsētas aptuvenās koordinātas
    private const RIGA = [56.95, 24.11];
    private const ADAZI = [57.07, 24.32];     // ~18 km no Rīgas
    private const JELGAVA = [56.65, 23.72];   // ~41 km
    private const VALMIERA = [57.54, 25.43];  // ~105 km

    private function userAt(?array $place, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);

        if ($place) {
            $user->forceFill(['latitude' => $place[0], 'longitude' => $place[1]])->save();
        }

        return $user;
    }

    public function test_location_is_rounded_to_about_one_km(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->putJson('/api/profile/location', ['latitude' => 56.949612, 'longitude' => 24.105186])
             ->assertOk()
             ->assertJsonPath('latitude', 56.95)
             ->assertJsonPath('longitude', 24.11);
    }

    public function test_invalid_location_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->putJson('/api/profile/location', ['latitude' => 120, 'longitude' => 24])
             ->assertStatus(422);
    }

    public function test_location_can_be_removed(): void
    {
        $user = $this->userAt(self::RIGA);

        $this->actingAs($user)->deleteJson('/api/profile/location')->assertOk();

        $this->assertFalse($user->fresh()->hasLocation());
    }

    public function test_coordinates_are_never_shown_to_others(): void
    {
        $me = $this->userAt(self::RIGA);
        $other = $this->userAt(self::JELGAVA);
        Book::factory()->create(['user_id' => $other->id, 'status' => 'Available']);

        $this->actingAs($me);

        foreach (["/api/users/{$other->id}", '/api/users', '/api/browse'] as $url) {
            $json = $this->getJson($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('latitude', $json, $url);
            $this->assertStringNotContainsString('23.72', $json, $url);
        }
    }

    public function test_books_sorted_by_nearest_owner(): void
    {
        $me = $this->userAt(self::RIGA);
        $far = $this->userAt(self::VALMIERA);
        $near = $this->userAt(self::ADAZI);
        $nowhere = $this->userAt(null);

        Book::factory()->create(['user_id' => $far->id, 'status' => 'Available', 'title' => 'Aaa']);
        Book::factory()->create(['user_id' => $nowhere->id, 'status' => 'Available', 'title' => 'Bbb']);
        Book::factory()->create(['user_id' => $near->id, 'status' => 'Available', 'title' => 'Ccc']);

        $response = $this->actingAs($me)->getJson('/api/browse?sort=nearest')->assertOk();

        $this->assertSame(['Ccc', 'Aaa', 'Bbb'], array_column($response->json('data'), 'title'));
        $this->assertTrue($response->json('has_location'));
        $this->assertEqualsWithDelta(18, $response->json('data.0.distance_km'), 3);
        $this->assertNull($response->json('data.2.distance_km'));
        $this->assertArrayHasKey('ratings_count', $response->json('data.0'));
    }

    public function test_books_filtered_by_distance(): void
    {
        $me = $this->userAt(self::RIGA);
        Book::factory()->create(['user_id' => $this->userAt(self::ADAZI)->id, 'status' => 'Available']);
        Book::factory()->create(['user_id' => $this->userAt(self::JELGAVA)->id, 'status' => 'Available']);
        Book::factory()->create(['user_id' => $this->userAt(self::VALMIERA)->id, 'status' => 'Available']);
        Book::factory()->create(['user_id' => $this->userAt(null)->id, 'status' => 'Available']);

        $this->actingAs($me);

        $this->getJson('/api/browse?max_km=25')->assertJsonPath('total', 1);
        $this->getJson('/api/browse?max_km=50')->assertJsonPath('total', 2);
        $this->getJson('/api/browse')->assertJsonPath('total', 4);
    }

    public function test_browse_without_own_location_ignores_distance(): void
    {
        $me = $this->userAt(null);
        Book::factory()->create(['user_id' => $this->userAt(self::RIGA)->id, 'status' => 'Available']);

        $this->actingAs($me)->getJson('/api/browse?sort=nearest&max_km=5')
             ->assertOk()
             ->assertJsonPath('total', 1)
             ->assertJsonPath('has_location', false);
    }

    public function test_users_sorted_and_filtered_by_distance(): void
    {
        $me = $this->userAt(self::RIGA);
        $this->userAt(self::VALMIERA, ['name' => 'Anna']);
        $this->userAt(self::ADAZI, ['name' => 'Zane']);
        $this->userAt(null, ['name' => 'Beate']);

        $this->actingAs($me);

        $names = array_column($this->getJson('/api/users?sort=nearest')->json('data'), 'name');
        $this->assertSame(['Zane', 'Anna', 'Beate'], $names);

        $this->getJson('/api/users?max_km=25')->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'Zane');
    }

    public function test_profile_shows_distance(): void
    {
        $me = $this->userAt(self::RIGA);
        $other = $this->userAt(self::JELGAVA);

        $distance = $this->actingAs($me)->getJson("/api/users/{$other->id}")->json('distance_km');

        $this->assertEqualsWithDelta(41, $distance, 3);
    }

    public function test_admin_sees_location(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->forceFill(['admin_password' => 'admin12345678'])->save();
        $this->userAt(self::JELGAVA, ['name' => 'Anna']);

        $token = $admin->createToken('auth_token');
        $token->accessToken->forceFill(['admin_until' => now()->addHour()])->save();

        $anna = collect($this->withToken($token->plainTextToken)->getJson('/api/admin/users')->assertOk()->json())
            ->firstWhere('name', 'Anna');

        $this->assertSame(56.65, $anna['latitude']);
        $this->assertSame(23.72, $anna['longitude']);
    }

    private function bookFor(User $owner, string $title, string $author = 'Autors', string $status = 'Available'): void
    {
        Book::factory()->create(['user_id' => $owner->id, 'title' => $title, 'author' => $author, 'status' => $status]);
    }

    public function test_map_shows_available_books_without_users(): void
    {
        $anna = $this->userAt(self::RIGA, ['name' => 'Anna']);
        $this->bookFor($anna, 'Dune');
        $this->bookFor($anna, 'Pending', status: 'Pending');

        $hidden = $this->userAt(self::VALMIERA, ['show_on_map' => false]);
        $blocked = $this->userAt(self::ADAZI, ['is_blocked' => true]);
        $nowhere = $this->userAt(null);
        foreach ([$hidden, $blocked, $nowhere] as $u) {
            $this->bookFor($u, 'Slepena');
        }

        $response = $this->getJson('/api/books/map')->assertOk();
        $map = $response->json();

        $this->assertCount(1, $map);
        $this->assertSame(['Dune'], array_column($map[0]['books'], 'title'));
        $this->assertSame(['lat', 'lng', 'books'], array_keys($map[0]));
        $this->assertStringNotContainsString('Anna', $response->getContent());
    }

    public function test_map_merges_same_book_in_region(): void
    {
        // abi vienā ~5 km šūnā
        $anna = $this->userAt([56.96, 24.10]);
        $janis = $this->userAt([56.99, 24.15]);
        $this->bookFor($anna, 'Dune', 'Herbert');
        $this->bookFor($janis, 'dune', 'herbert');
        $this->bookFor($janis, 'Hobits');

        $map = $this->getJson('/api/books/map')->json();

        $this->assertCount(1, $map);
        $books = collect($map[0]['books'])->keyBy(fn($b) => strtolower($b['title']));
        $this->assertCount(2, $books);
        $this->assertSame(2, $books['dune']['copies']);
        $this->assertSame(1, $books['hobits']['copies']);
        // vieta noapaļota, nevis kāda no īstajām
        $this->assertNotEquals(56.96, $map[0]['lat']);
        $this->assertNotEquals(56.99, $map[0]['lat']);
    }

    public function test_map_hides_my_books_and_books_i_have(): void
    {
        $me = $this->userAt(self::RIGA);
        $other = $this->userAt(self::JELGAVA);
        $this->bookFor($me, 'Dune');
        $this->bookFor($other, 'Dune');
        $this->bookFor($other, 'Hobits');

        $titles = collect($this->actingAs($me)->getJson('/api/books/map')->json())
            ->flatMap(fn($p) => array_column($p['books'], 'title'));

        $this->assertSame(['Hobits'], $titles->all());
    }

    public function test_map_can_be_turned_off(): void
    {
        $me = $this->userAt(self::RIGA);
        $this->bookFor($me, 'Dune');

        $this->getJson('/api/books/map')->assertJsonCount(1);

        $this->actingAs($me)->patchJson('/api/profile/privacy', [
            'show_joined' => true, 'show_swaps' => true, 'show_swap_history' => true, 'show_on_map' => false,
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/books/map')->assertJsonCount(0);
    }

    public function test_account_deletion_removes_location(): void
    {
        $user = $this->userAt(self::RIGA);

        $this->actingAs($user)->deleteJson('/api/profile', ['password' => 'password'])->assertNoContent();

        $this->assertFalse($user->fresh()->hasLocation());
    }
}
