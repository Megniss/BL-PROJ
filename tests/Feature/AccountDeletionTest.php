<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Book;
use App\Models\Message;
use App\Models\Rating;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\SwapUnavailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->deleteJson('/api/profile', ['password' => 'password'])->assertUnauthorized();
    }

    public function test_wrong_password_does_not_delete(): void
    {
        $user = User::factory()->create(['name' => 'Anna']);

        $this->actingAs($user)
             ->deleteJson('/api/profile', ['password' => 'wrong'])
             ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Anna', 'deleted_at' => null]);
    }

    public function test_admin_cannot_delete_account(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
             ->deleteJson('/api/profile', ['password' => 'password'])
             ->assertForbidden();

        $this->assertNull($admin->fresh()->deleted_at);
    }

    public function test_user_is_anonymized(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);
        $user->createToken('auth_token');
        $book = Book::factory()->create(['user_id' => $user->id, 'status' => 'Available']);

        $this->actingAs($user)
             ->deleteJson('/api/profile', ['password' => 'password'])
             ->assertNoContent();

        $user->refresh();
        $this->assertSame('Deleted user', $user->name);
        $this->assertNotSame('anna@example.com', $user->email);
        $this->assertNotNull($user->deleted_at);
        $this->assertCount(0, $user->tokens);
        $this->assertSame('Removed', $book->fresh()->status);
    }

    public function test_email_can_be_used_again(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->actingAs($user)->deleteJson('/api/profile', ['password' => 'password']);

        $this->postJson('/api/register', [
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertCreated();
    }

    public function test_history_ratings_and_messages_stay_for_other_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        // apmaiņa jau notikusi — grāmatas samainītas
        $gaveAway = Book::factory()->create(['user_id' => $other->id, 'status' => 'Available']);
        $received = Book::factory()->create(['user_id' => $user->id, 'status' => 'Available']);

        $swap = SwapRequest::create([
            'requester_id' => $user->id,
            'owner_id' => $other->id,
            'offered_book_id' => $gaveAway->id,
            'wanted_book_id' => $received->id,
            'status' => 'accepted',
        ]);

        $rating = Rating::create([
            'swap_request_id' => $swap->id,
            'book_id' => $received->id,
            'rater_id' => $user->id,
            'stars' => 4,
        ]);

        $message = Message::create(['from_user_id' => $user->id, 'to_user_id' => $other->id, 'body' => 'Sveiki']);

        $this->actingAs($user)->deleteJson('/api/profile', ['password' => 'password'])->assertNoContent();

        $this->assertDatabaseHas('swap_requests', ['id' => $swap->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('ratings', ['id' => $rating->id]);
        $this->assertDatabaseHas('messages', ['id' => $message->id]);
        $this->assertSame($other->id, $gaveAway->fresh()->user_id);

        $this->actingAs($other)->getJson('/api/profile/history')
             ->assertOk()
             ->assertJsonPath('data.0.requester.name', 'Deleted user');

        $this->actingAs($other)->getJson('/api/messages')
             ->assertOk()
             ->assertJsonPath('0.user.name', 'Deleted user')
             ->assertJsonPath('0.user.deleted', true);
    }

    public function test_pending_swaps_are_declined_and_other_user_notified(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Book::factory()->create(['user_id' => $user->id, 'status' => 'Pending']);
        $theirs = Book::factory()->create(['user_id' => $other->id, 'status' => 'Pending']);

        $swap = SwapRequest::create([
            'requester_id' => $other->id,
            'owner_id' => $user->id,
            'offered_book_id' => $theirs->id,
            'wanted_book_id' => $mine->id,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->deleteJson('/api/profile', ['password' => 'password'])->assertNoContent();

        $this->assertSame('declined', $swap->fresh()->status);
        $this->assertSame('Available', $theirs->fresh()->status);
        $this->assertSame('Removed', $mine->fresh()->status);
        Notification::assertSentTo($other, SwapUnavailable::class);
    }

    public function test_deleted_user_is_hidden_everywhere(): void
    {
        $user = User::factory()->create(['name' => 'Anna']);
        $other = User::factory()->create();
        Book::factory()->create(['user_id' => $user->id, 'status' => 'Available']);
        Block::create(['blocker_id' => $other->id, 'blocked_id' => $user->id]);

        $this->actingAs($user)->deleteJson('/api/profile', ['password' => 'password'])->assertNoContent();

        $this->assertDatabaseMissing('blocks', ['blocked_id' => $user->id]);
        $this->getJson("/api/users/{$user->id}")->assertNotFound();
        $this->getJson('/api/users?search=Deleted')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/browse')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($other)
             ->postJson('/api/messages', ['to_user_id' => $user->id, 'body' => 'Čau'])
             ->assertStatus(422);
    }
}
