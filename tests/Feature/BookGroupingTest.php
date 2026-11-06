<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Rating;
use App\Models\SwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function copy(User $owner, string $title = 'HarryPotter', string $author = 'JkRowling', array $attrs = []): Book
    {
        return Book::factory()->create($attrs + [
            'user_id' => $owner->id,
            'title' => $title,
            'author' => $author,
            'status' => 'Available',
        ]);
    }

    public function test_same_book_from_several_users_is_one_card(): void
    {
        $this->copy(User::factory()->create());
        $this->copy(User::factory()->create());
        $this->copy(User::factory()->create());
        $this->copy(User::factory()->create(), 'Dune', 'FrankHerbert');

        $response = $this->getJson('/api/browse')->assertOk();

        $this->assertSame(2, $response->json('total'));
        $harry = collect($response->json('data'))->firstWhere('title', 'HarryPotter');
        $this->assertSame(3, $harry['copies_count']);
    }

    public function test_matching_ignores_letter_case(): void
    {
        $this->copy(User::factory()->create(), 'HarryPotter', 'JkRowling');
        $this->copy(User::factory()->create(), 'harrypotter', 'jkrowling');

        $this->getJson('/api/browse')->assertJsonPath('total', 1)->assertJsonPath('data.0.copies_count', 2);
    }

    public function test_pending_copies_are_not_shown(): void
    {
        $this->copy(User::factory()->create());
        $this->copy(User::factory()->create(), attrs: ['status' => 'Pending']);
        $this->copy(User::factory()->create(), 'Dune', 'FrankHerbert', ['status' => 'Pending']);

        $this->getJson('/api/browse')
             ->assertJsonPath('total', 1)
             ->assertJsonPath('data.0.copies_count', 1);

        $this->getJson('/api/browse/copies?title=HarryPotter&author=JkRowling')
             ->assertJsonCount(1, 'copies');
    }

    public function test_book_i_already_have_is_hidden(): void
    {
        $me = User::factory()->create();
        $this->copy($me);
        $this->copy(User::factory()->create());
        $this->copy(User::factory()->create(), 'Dune', 'FrankHerbert');

        $this->actingAs($me);

        $titles = array_column($this->getJson('/api/browse')->json('data'), 'title');
        $this->assertSame(['Dune'], $titles);

        $this->getJson('/api/browse/copies?title=HarryPotter&author=JkRowling')
             ->assertJsonPath('i_have', true)
             ->assertJsonCount(0, 'copies');
    }

    public function test_copies_list_every_owner(): void
    {
        $anna = User::factory()->create(['name' => 'Anna']);
        $janis = User::factory()->create(['name' => 'Janis']);
        $this->copy($anna, attrs: ['language' => 'Latvian']);
        $this->copy($janis, attrs: ['language' => 'English']);

        $copies = collect($this->getJson('/api/browse/copies?title=HarryPotter&author=JkRowling')->json('copies'));

        $this->assertEqualsCanonicalizing(['Anna', 'Janis'], $copies->pluck('user.name')->all());
        $this->assertEqualsCanonicalizing(['Latvian', 'English'], $copies->pluck('language')->all());
    }

    public function test_filter_matches_if_any_copy_matches(): void
    {
        $this->copy(User::factory()->create(), attrs: ['language' => 'Latvian']);
        $this->copy(User::factory()->create(), attrs: ['language' => 'English']);

        $this->getJson('/api/browse?languages[]=Latvian')
             ->assertJsonPath('total', 1)
             ->assertJsonPath('data.0.copies_count', 1);
    }

    public function test_card_rating_combines_all_copies(): void
    {
        $owner1 = User::factory()->create();
        $owner2 = User::factory()->create();
        $a = $this->copy($owner1);
        $b = $this->copy($owner2);

        foreach ([[$a, 5], [$b, 3]] as [$book, $stars]) {
            $rater = User::factory()->create();
            $swap = SwapRequest::create([
                'requester_id' => $rater->id, 'owner_id' => $book->user_id,
                'offered_book_id' => Book::factory()->create(['user_id' => $rater->id])->id,
                'wanted_book_id' => $book->id, 'status' => 'accepted',
            ]);
            Rating::create(['swap_request_id' => $swap->id, 'book_id' => $book->id, 'rater_id' => $rater->id, 'stars' => $stars]);
        }

        $harry = collect($this->getJson('/api/browse')->json('data'))->firstWhere('title', 'HarryPotter');

        $this->assertSame(2, $harry['ratings_count']);
        $this->assertEquals(4, $harry['ratings_avg_stars']);
    }

    public function test_suggestions_skip_same_book_and_owned_books(): void
    {
        $me = User::factory()->create();
        $this->copy($me, 'Dune', 'FrankHerbert', ['genre' => 'Fantasy']);
        $book = $this->copy(User::factory()->create(), attrs: ['genre' => 'Fantasy']);
        $this->copy(User::factory()->create(), attrs: ['genre' => 'Fantasy']);
        $this->copy(User::factory()->create(), 'Dune', 'FrankHerbert', ['genre' => 'Fantasy']);
        $this->copy(User::factory()->create(), 'Hobbit', 'Tolkien', ['genre' => 'Fantasy']);
        $this->copy(User::factory()->create(), 'Hobbit', 'Tolkien', ['genre' => 'Fantasy']);

        $titles = array_column($this->actingAs($me)->getJson("/api/books/{$book->id}/suggestions")->json(), 'title');

        $this->assertSame(['Hobbit'], $titles);
    }

    public function test_autocomplete_suggests_existing_books(): void
    {
        $me = User::factory()->create();
        $this->copy($me);
        $this->copy(User::factory()->create());
        $this->copy(User::factory()->create(), 'HarryHole', 'JoNesbo');
        $this->copy(User::factory()->create(), 'Dune', 'FrankHerbert');

        $this->actingAs($me);

        $this->getJson('/api/books/autocomplete?q=h')->assertJsonCount(0);

        $results = collect($this->getJson('/api/books/autocomplete?q=harry')->json());
        $this->assertCount(2, $results);
        $this->assertTrue($results->firstWhere('title', 'HarryPotter')['mine']);
        $this->assertFalse($results->firstWhere('title', 'HarryHole')['mine']);

        // atstarpes tiek ignorētas, jo glabājot tās izņem
        $this->assertCount(1, $this->getJson('/api/books/autocomplete?q=harry pot')->json());

        $this->assertSame(['JoNesbo'], $this->getJson('/api/books/authors?q=nes')->json());
    }

    public function test_profile_marks_books_i_have(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->copy($me);
        $this->copy($other);
        $this->copy($other, 'Dune', 'FrankHerbert');

        $library = collect($this->actingAs($me)->getJson("/api/users/{$other->id}")->json('library'));

        $this->assertTrue($library->firstWhere('title', 'HarryPotter')['i_have']);
        $this->assertFalse($library->firstWhere('title', 'Dune')['i_have']);
    }
}
