<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Rating;
use App\Models\SwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingTest extends TestCase
{
    use RefreshDatabase;

    // Creates two users each with a book, creates an accepted swap between them
    private function createAcceptedSwap(): array
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();

        $offeredBook = Book::factory()->create(['user_id' => $requester->id, 'status' => 'Available']);
        $wantedBook  = Book::factory()->create(['user_id' => $owner->id, 'status' => 'Available']);

        $swap = SwapRequest::create([
            'requester_id'   => $requester->id,
            'owner_id'       => $owner->id,
            'offered_book_id'=> $offeredBook->id,
            'wanted_book_id' => $wantedBook->id,
            'status'         => 'accepted',
        ]);

        // simulate ownership transfer that accept() would have done
        $offeredBook->update(['user_id' => $owner->id, 'status' => 'Swapped']);
        $wantedBook->update(['user_id' => $requester->id, 'status' => 'Swapped']);

        return [$requester, $owner, $swap, $offeredBook, $wantedBook];
    }

    public function test_receiver_can_rate_a_swap(): void
    {
        [$requester, $owner, $swap, $offeredBook, $wantedBook] = $this->createAcceptedSwap();

        $response = $this->actingAs($requester)
            ->postJson('/api/ratings', [
                'swap_request_id' => $swap->id,
                'stars' => 4,
                'review' => 'Great book!',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ratings', [
            'swap_request_id' => $swap->id,
            'book_id'         => $wantedBook->id,
            'rater_id'        => $requester->id,
            'stars'           => 4,
        ]);
    }

    public function test_owner_can_rate_the_book_they_received(): void
    {
        [$requester, $owner, $swap, $offeredBook, $wantedBook] = $this->createAcceptedSwap();

        $response = $this->actingAs($owner)
            ->postJson('/api/ratings', [
                'swap_request_id' => $swap->id,
                'stars' => 5,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ratings', [
            'swap_request_id' => $swap->id,
            'book_id'         => $offeredBook->id,
            'rater_id'        => $owner->id,
            'stars'           => 5,
        ]);
    }

    public function test_duplicate_rating_returns_422(): void
    {
        [$requester, $owner, $swap, $offeredBook, $wantedBook] = $this->createAcceptedSwap();

        $this->actingAs($requester)->postJson('/api/ratings', [
            'swap_request_id' => $swap->id,
            'stars' => 4,
        ])->assertStatus(201);

        $this->actingAs($requester)->postJson('/api/ratings', [
            'swap_request_id' => $swap->id,
            'stars' => 3,
        ])->assertStatus(422);
    }

    public function test_non_participant_gets_403(): void
    {
        [$requester, $owner, $swap] = $this->createAcceptedSwap();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson('/api/ratings', [
                'swap_request_id' => $swap->id,
                'stars' => 3,
            ])
            ->assertStatus(403);
    }

    public function test_cannot_rate_pending_swap(): void
    {
        $requester = User::factory()->create();
        $owner = User::factory()->create();
        $offered = Book::factory()->create(['user_id' => $requester->id]);
        $wanted  = Book::factory()->create(['user_id' => $owner->id]);

        $swap = SwapRequest::create([
            'requester_id'    => $requester->id,
            'owner_id'        => $owner->id,
            'offered_book_id' => $offered->id,
            'wanted_book_id'  => $wanted->id,
            'status'          => 'pending',
        ]);

        $this->actingAs($requester)
            ->postJson('/api/ratings', [
                'swap_request_id' => $swap->id,
                'stars' => 4,
            ])
            ->assertStatus(422);
    }

    public function test_rating_without_review_succeeds(): void
    {
        [$requester, $owner, $swap] = $this->createAcceptedSwap();

        $this->actingAs($requester)
            ->postJson('/api/ratings', [
                'swap_request_id' => $swap->id,
                'stars' => 3,
            ])
            ->assertStatus(201);
    }

    public function test_unauthenticated_rating_returns_401(): void
    {
        $this->postJson('/api/ratings', [
            'swap_request_id' => 1,
            'stars' => 4,
        ])->assertStatus(401);
    }

    public function test_browse_includes_rating_averages(): void
    {
        $rater = User::factory()->create();
        $owner = User::factory()->create();
        $offeredBook = Book::factory()->create(['user_id' => $rater->id, 'status' => 'Available']);
        $wantedBook  = Book::factory()->create(['user_id' => $owner->id, 'status' => 'Available']);

        $swap = SwapRequest::create([
            'requester_id'    => $rater->id,
            'owner_id'        => $owner->id,
            'offered_book_id' => $offeredBook->id,
            'wanted_book_id'  => $wantedBook->id,
            'status'          => 'accepted',
        ]);

        Rating::create([
            'swap_request_id' => $swap->id,
            'book_id'         => $wantedBook->id,
            'rater_id'        => $rater->id,
            'stars'           => 5,
        ]);

        // mark books as Available so they appear in browse
        $offeredBook->update(['status' => 'Available']);
        $wantedBook->update(['status' => 'Available']);

        $response = $this->getJson('/api/browse');
        $response->assertOk();

        $items = $response->json('data');
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertArrayHasKey('ratings_avg_stars', $item);
            $this->assertArrayHasKey('ratings_count', $item);
        }
    }
}
