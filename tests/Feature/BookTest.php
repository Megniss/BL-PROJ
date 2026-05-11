<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    private function bookData(array $overrides = []): array
    {
        return array_merge([
            'title'       => 'Test Book',
            'author'      => 'Test Author',
            'genre'       => 'Klasika',
            'language'    => 'English',
            'condition'   => 'Good',
            'status'      => 'Available',
            'description' => 'A great book.',
        ], $overrides);
    }

    public function test_user_can_add_a_book(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
             ->postJson('/api/books', $this->bookData());

        $response->assertStatus(201)
                 ->assertJsonFragment(['title' => 'TestBook']);

        $this->assertDatabaseHas('books', ['title' => 'TestBook', 'user_id' => $user->id]);
    }

    public function test_add_book_requires_authentication(): void
    {
        $this->postJson('/api/books', $this->bookData())->assertUnauthorized();
    }

    public function test_add_book_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->postJson('/api/books', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['title', 'author', 'genre', 'language', 'condition']);
    }

    public function test_user_can_list_own_books(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Book::factory()->count(3)->create(['user_id' => $user->id]);
        Book::factory()->count(2)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->getJson('/api/books');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_user_can_edit_own_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id, 'title' => 'Old Title']);

        $this->actingAs($user)
             ->putJson("/api/books/{$book->id}", $this->bookData(['title' => 'New Title']))
             ->assertOk()
             ->assertJsonFragment(['title' => 'NewTitle']);
    }

    public function test_user_cannot_edit_another_users_book(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book  = Book::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
             ->putJson("/api/books/{$book->id}", $this->bookData())
             ->assertForbidden();
    }

    public function test_user_can_delete_own_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id, 'status' => 'Available']);

        $this->actingAs($user)
             ->deleteJson("/api/books/{$book->id}")
             ->assertOk();

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_user_cannot_delete_pending_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id, 'status' => 'Pending']);

        $this->actingAs($user)
             ->deleteJson("/api/books/{$book->id}")
             ->assertStatus(422);
    }

    public function test_user_cannot_delete_another_users_book(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $book  = Book::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
             ->deleteJson("/api/books/{$book->id}")
             ->assertForbidden();
    }

    public function test_browse_returns_available_books(): void
    {
        $user = User::factory()->create();

        Book::factory()->count(5)->create(['user_id' => $user->id, 'status' => 'Available']);
        Book::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'Pending']);
        Book::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'Swapped']);

        $response = $this->getJson('/api/browse');

        $response->assertOk();

        // Swapped books must never appear
        foreach ($response->json('data') as $book) {
            $this->assertNotSame('Swapped', $book['status']);
        }
    }

    public function test_browse_filters_by_search(): void
    {
        Book::factory()->create(['title' => 'Unique Title XYZ', 'status' => 'Available']);
        Book::factory()->create(['title' => 'Other Book', 'status' => 'Available']);

        $response = $this->getJson('/api/browse?search=Unique+Title');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}