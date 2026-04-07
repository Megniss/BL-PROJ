<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\SwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwapRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeSwap(User $requester, User $owner): array
    {
        $offered = Book::factory()->create(['user_id' => $requester->id, 'status' => 'Available']);
        $wanted  = Book::factory()->create(['user_id' => $owner->id,     'status' => 'Available']);

        return compact('offered', 'wanted');
    }

    public function test_user_can_create_swap_request(): void
    {
        $requester = User::factory()->create();
        $owner     = User::factory()->create();
        ['offered' => $offered, 'wanted' => $wanted] = $this->makeSwap($requester, $owner);

        $response = $this->actingAs($requester)->postJson('/api/swap-requests', [
            'offered_book_id' => $offered->id,
            'wanted_book_id'  => $wanted->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('swap_requests', [
            'requester_id'    => $requester->id,
            'offered_book_id' => $offered->id,
            'wanted_book_id'  => $wanted->id,
            'status'          => 'pending',
        ]);
    }

    public function test_cannot_swap_own_book(): void
    {
        $user    = User::factory()->create();
        $offered = Book::factory()->create(['user_id' => $user->id, 'status' => 'Available']);
        $wanted  = Book::factory()->create(['user_id' => $user->id, 'status' => 'Available']);

        $this->actingAs($user)->postJson('/api/swap-requests', [
            'offered_book_id' => $offered->id,
            'wanted_book_id'  => $wanted->id,
        ])->assertStatus(422);
    }

    public function test_cannot_duplicate_swap_request(): void
    {
        $requester = User::factory()->create();
        $owner     = User::factory()->create();
        ['offered' => $offered, 'wanted' => $wanted] = $this->makeSwap($requester, $owner);

        SwapRequest::create([
            'requester_id'    => $requester->id,
            'owner_id'        => $owner->id,
            'offered_book_id' => $offered->id,
            'wanted_book_id'  => $wanted->id,
            'status'          => 'pending',
        ]);

        $this->actingAs($requester)->postJson('/api/swap-requests', [
            'offered_book_id' => $offered->id,
            'wanted_book_id'  => $wanted->id,
        ])->assertStatus(422);
    }

    public function test_owner_can_accept_swap(): void
    {
        $requester = User::factory()->create();
        $owner     = User::factory()->create();
        ['offered' => $offered, 'wanted' => $wanted] = $this->makeSwap($requester, $owner);

        $swap = SwapRequest::create([
            'requester_id'    => $requester->id,
            'owner_id'        => $owner->id,
            'offered_book_id' => $offered->id,
            'wanted_book_id'  => $wanted->id,
            'status'          => 'pending',
        ]);

        $this->actingAs($owner)
             ->patchJson("/api/swap-requests/{$swap->id}/accept")
             ->assertOk();

        $this->assertDatabaseHas('swap_requests', ['id' => $swap->id, 'status' => 'accepted']);

        // Books should have changed ownership
        $this->assertDatabaseHas('books', ['id' => $offered->id, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('books', ['id' => $wanted->id,  'user_id' => $requester->id]);
    }

    public function test_owner_can_decline_swap(): void
    {
        $requester = User::factory()->create();
        $owner     = User::factory()->create();
        ['offered' => $offered, 'wanted' => $wanted] = $this->makeSwap($requester, $owner);

        $swap = SwapRequest::create([
            'requester_id'    => $requester->id,
            'owner_id'        => $owner->id,
            'offered_book_id' => $offered->id,
            'wanted_book_id'  => $wanted->id,
            'status'          => 'pending',
        ]);

        $this->actingAs($owner)
             ->patchJson("/api/swap-requests/{$swap->id}/decline")
             ->assertOk();

        $this->assertDatabaseHas('swap_requests', ['id' => $swap->id, 'status' => 'declined']);

        // Books should be available again
        $this->assertDatabaseHas('books', ['id' => $offered->id, 'status' => 'Available']);
        $this->assertDatabaseHas('books', ['id' => $wanted->id,  'status' => 'Available']);
    }

    public function test_non_owner_cannot_accept_swap(): void
    {
        $requester = User::factory()->create();
        $owner     = User::factory()->create();
        $stranger  = User::factory()->create();
        ['offered' => $offered, 'wanted' => $wanted] = $this->makeSwap($requester, $owner);

        $swap = SwapRequest::create([
            'requester_id'    => $requester->id,
            'owner_id'        => $owner->id,
            'offered_book_id' => $offered->id,
            'wanted_book_id'  => $wanted->id,
            'status'          => 'pending',
        ]);

        $this->actingAs($stranger)
             ->patchJson("/api/swap-requests/{$swap->id}/accept")
             ->assertForbidden();
    }

    public function test_requester_can_cancel_pending_swap(): void
    {
        $requester = User::factory()->create();
        $owner     = User::factory()->create();
        ['offered' => $offered, 'wanted' => $wanted] = $this->makeSwap($requester, $owner);

        // Create the swap via the API so both books are set to Pending
        $response = $this->actingAs($requester)->postJson('/api/swap-requests', [
            'offered_book_id' => $offered->id,
            'wanted_book_id'  => $wanted->id,
        ]);
        $response->assertStatus(201);

        $this->assertDatabaseHas('books', ['id' => $offered->id, 'status' => 'Pending']);
        $this->assertDatabaseHas('books', ['id' => $wanted->id,  'status' => 'Pending']);

        $swapId = $response->json('id');

        $this->actingAs($requester)
             ->deleteJson("/api/swap-requests/{$swapId}")
             ->assertOk();

        // Cancelling a pending request marks it declined + dismissed (not deleted)
        $this->assertDatabaseHas('swap_requests', [
            'id'                   => $swapId,
            'status'               => 'declined',
            'requester_dismissed'  => true,
        ]);

        // Both books must be Available again after cancellation
        $this->assertDatabaseHas('books', ['id' => $offered->id, 'status' => 'Available']);
        $this->assertDatabaseHas('books', ['id' => $wanted->id,  'status' => 'Available']);
    }
}
