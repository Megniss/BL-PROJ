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

    public function test_account_deletion_removes_location(): void
    {
        $user = $this->userAt(self::RIGA);

        $this->actingAs($user)->deleteJson('/api/profile', ['password' => 'password'])->assertNoContent();

        $this->assertFalse($user->fresh()->hasLocation());
    }
}
