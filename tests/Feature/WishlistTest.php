<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Book;
use App\Models\User;
use App\Models\Wishlist;
use App\Notifications\WishlistMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

// vēlmju saraksts: pievienošana, paziņojumi, kad kāds pievieno grāmatu
class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private function addBook(User $user, string $title = 'Mazais Princis', string $author = 'Antuāns de Sent-Ekziperī')
    {
        return $this->actingAs($user)->postJson('/api/books', [
            'title' => $title,
            'author' => $author,
            'genre' => 'Klasika',
            'language' => 'Latviešu',
            'condition' => 'Good',
        ]);
    }

    public function test_user_can_add_and_remove_wish(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/api/wishlist', ['title' => 'mazais princis', 'author' => 'Antuāns de Sent-Ekziperī'])
            ->assertCreated();
        // saglabā tāpat kā grāmatas
        $this->assertSame('MazaisPrincis', $res->json('title'));

        $this->actingAs($user)->getJson('/api/wishlist')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.available', 0);

        $this->actingAs($user)->deleteJson('/api/wishlist/' . $res->json('id'))->assertOk();
        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_duplicate_wish_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/wishlist', ['title' => 'Bille', 'author' => 'Vizma Belševica'])->assertCreated();
        $this->actingAs($user)->postJson('/api/wishlist', ['title' => 'bille', 'author' => 'vizma belševica'])
            ->assertStatus(422)->assertJsonValidationErrors('title');
    }

    public function test_cannot_wish_own_book(): void
    {
        $user = User::factory()->create();
        $this->addBook($user, 'Bille', 'Vizma Belševica')->assertCreated();

        $this->actingAs($user)->postJson('/api/wishlist', ['title' => 'Bille', 'author' => 'Vizma Belševica'])
            ->assertStatus(422);
    }

    public function test_cannot_delete_someone_elses_wish(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $wish = Wishlist::create(['user_id' => $owner->id, 'title' => 'Bille', 'author' => 'VizmaBelševica']);

        $this->actingAs($other)->deleteJson("/api/wishlist/{$wish->id}")->assertForbidden();
        $this->assertDatabaseHas('wishlists', ['id' => $wish->id]);
    }

    public function test_new_book_notifies_users_who_wish_it(): void
    {
        Notification::fake();
        $wisher = User::factory()->create();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($wisher)->postJson('/api/wishlist', ['title' => 'Mazais Princis', 'author' => 'Antuāns de Sent-Ekziperī']);

        $this->addBook($owner)->assertCreated();

        Notification::assertSentTo($wisher, WishlistMatch::class, fn($n, $channels) => in_array('mail', $channels));
        Notification::assertNotSentTo($stranger, WishlistMatch::class);
        $this->assertNotNull(Wishlist::first()->notified_at);

        // tagad redz, ka ir pieejama
        $this->actingAs($wisher)->getJson('/api/wishlist')->assertJsonPath('0.available', 1);
    }

    public function test_second_copy_same_day_sends_no_second_email(): void
    {
        Notification::fake();
        $wisher = User::factory()->create();
        $this->actingAs($wisher)->postJson('/api/wishlist', ['title' => 'Bille', 'author' => 'Vizma Belševica']);

        $this->addBook(User::factory()->create(), 'Bille', 'Vizma Belševica');
        $this->addBook(User::factory()->create(), 'Bille', 'Vizma Belševica');

        $sent = Notification::sent($wisher, WishlistMatch::class);
        $this->assertCount(2, $sent);
        $this->assertSame(['database', 'mail'], $sent[0]->via($wisher));
        $this->assertSame(['database'], $sent[1]->via($wisher));
    }

    public function test_blocked_owner_does_not_trigger_notification(): void
    {
        Notification::fake();
        $wisher = User::factory()->create();
        $owner = User::factory()->create();
        Block::create(['blocker_id' => $wisher->id, 'blocked_id' => $owner->id]);

        $this->actingAs($wisher)->postJson('/api/wishlist', ['title' => 'Bille', 'author' => 'Vizma Belševica']);
        $this->addBook($owner, 'Bille', 'Vizma Belševica');

        Notification::assertNotSentTo($wisher, WishlistMatch::class);
    }

    public function test_adding_book_yourself_removes_it_from_wishlist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/wishlist', ['title' => 'Bille', 'author' => 'Vizma Belševica']);

        $this->addBook($user, 'Bille', 'Vizma Belševica')->assertCreated();

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_notification_links_to_book_in_browse(): void
    {
        $wisher = User::factory()->create();
        $this->actingAs($wisher)->postJson('/api/wishlist', ['title' => 'Bille', 'author' => 'Vizma Belševica']);
        $this->addBook(User::factory()->create(), 'Bille', 'Vizma Belševica');

        $res = $this->actingAs($wisher)->getJson('/api/notifications')->assertOk();
        $this->assertSame('wishlist', $res->json('data.0.kind'));
        $this->assertSame('/browse?title=Bille&author=VizmaBel%C5%A1evica', $res->json('data.0.link'));
    }

    public function test_guest_cannot_use_wishlist(): void
    {
        $this->getJson('/api/wishlist')->assertUnauthorized();
        $this->postJson('/api/wishlist', ['title' => 'Bille', 'author' => 'X'])->assertUnauthorized();
    }
}
