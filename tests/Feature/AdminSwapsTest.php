<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\SwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// admin panelī apmaiņām jāredz abas puses un kurš apmaiņu pieņēma/noraidīja
class AdminSwapsTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('auth_token');
        $token->accessToken->forceFill(['admin_until' => now()->addHour()])->save();

        return $token->plainTextToken;
    }

    private function as(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    // pieprasītājs piedāvā savu grāmatu par īpašnieka grāmatu
    private function makeSwap(User $requester, User $owner, string $status = 'Pending'): SwapRequest
    {
        $offered = Book::factory()->create(['user_id' => $requester->id, 'status' => $status]);
        $wanted = Book::factory()->create(['user_id' => $owner->id, 'status' => $status]);

        return SwapRequest::create([
            'requester_id' => $requester->id,
            'owner_id' => $owner->id,
            'offered_book_id' => $offered->id,
            'wanted_book_id' => $wanted->id,
            'status' => 'pending',
        ]);
    }

    private function row(string $token, int $id): array
    {
        return collect($this->as($token)->getJson('/api/admin/swaps')->assertOk()->json())
            ->firstWhere('id', $id);
    }

    public function test_list_shows_both_users_and_books(): void
    {
        $anna = User::factory()->create(['name' => 'Anna']);
        $janis = User::factory()->create(['name' => 'Jānis']);
        $swap = $this->makeSwap($anna, $janis);
        $swap->forceFill(['created_at' => now()->subDays(9)])->save();

        $row = $this->row($this->adminToken(), $swap->id);

        $this->assertSame('Anna', $row['requester']['name']);
        $this->assertSame('Jānis', $row['owner']['name']);
        $this->assertSame($swap->offeredBook->title, $row['offered']['title']);
        $this->assertSame($swap->wantedBook->author, $row['wanted']['author']);
        $this->assertSame(9, $row['days_waiting']);
        $this->assertNull($row['closed_by']);
    }

    public function test_owner_decline_and_requester_cancel_are_recorded(): void
    {
        $anna = User::factory()->create();
        $janis = User::factory()->create();
        $declined = $this->makeSwap($anna, $janis);
        $cancelled = $this->makeSwap($anna, $janis);

        $this->actingAs($janis)->patchJson("/api/swap-requests/{$declined->id}/decline")->assertOk();
        $this->actingAs($anna)->deleteJson("/api/swap-requests/{$cancelled->id}")->assertOk();

        $token = $this->adminToken();
        $this->assertSame('owner', $this->row($token, $declined->id)['closed_by']);
        $this->assertSame('requester', $this->row($token, $cancelled->id)['closed_by']);
        $this->assertNotNull($this->row($token, $declined->id)['closed_at']);
    }

    public function test_accept_closes_other_requests_automatically(): void
    {
        $anna = User::factory()->create();
        $janis = User::factory()->create();
        $liga = User::factory()->create();
        $swap = $this->makeSwap($anna, $janis);

        // Līga arī grib to pašu Jāņa grāmatu
        $ligasBook = Book::factory()->create(['user_id' => $liga->id, 'status' => 'Pending']);
        $other = SwapRequest::create([
            'requester_id' => $liga->id,
            'owner_id' => $janis->id,
            'offered_book_id' => $ligasBook->id,
            'wanted_book_id' => $swap->wanted_book_id,
            'status' => 'pending',
        ]);

        $token = $this->adminToken();
        $this->as($token)->patchJson("/api/admin/swaps/{$swap->id}/accept")->assertOk();

        $this->assertSame('admin', $this->row($token, $swap->id)['closed_by']);
        $this->assertSame('system', $this->row($token, $other->id)['closed_by']);
        $this->assertSame('declined', $this->row($token, $other->id)['status']);
    }

    // vecajām apmaiņām bez closed_by migrācija ieliek minējumu
    public function test_old_swaps_get_a_guess(): void
    {
        $anna = User::factory()->create();
        $janis = User::factory()->create();
        $accepted = $this->makeSwap($anna, $janis);
        $cancelled = $this->makeSwap($anna, $janis);
        $declined = $this->makeSwap($anna, $janis);
        $pending = $this->makeSwap($anna, $janis);
        $accepted->update(['status' => 'accepted']);
        $cancelled->update(['status' => 'declined', 'requester_dismissed' => true]);
        $declined->update(['status' => 'declined']);

        (require database_path('migrations/2026_11_25_000003_guess_closed_by_for_old_swaps.php'))->up();

        $this->assertSame('owner', $accepted->fresh()->closed_by);
        $this->assertSame('requester', $cancelled->fresh()->closed_by);
        $this->assertSame('owner', $declined->fresh()->closed_by);
        $this->assertNotNull($declined->fresh()->closed_at);
        $this->assertNull($pending->fresh()->closed_by);
    }

    // vērtējumu tabā redz, kurš vērtēja, kuru grāmatu un no kā to saņēma
    public function test_ratings_show_rater_book_and_giver(): void
    {
        $anna = User::factory()->create(['name' => 'Anna']);
        $janis = User::factory()->create(['name' => 'Jānis']);
        $swap = $this->makeSwap($anna, $janis);

        $this->actingAs($janis)->patchJson("/api/swap-requests/{$swap->id}/accept")->assertOk();
        // Anna saņēma Jāņa grāmatu
        $this->actingAs($anna)->postJson('/api/ratings', ['swap_request_id' => $swap->id, 'stars' => 4, 'review' => 'Laba'])->assertCreated();

        $this->app['auth']->forgetGuards();
        $row = $this->as($this->adminToken())->getJson('/api/admin/ratings')->assertOk()->json(0);

        $this->assertSame('Anna', $row['rater']['name']);
        $this->assertSame('Jānis', $row['giver']['name']);
        $this->assertSame($swap->wantedBook->title, $row['book']['title']);
        $this->assertSame($swap->id, $row['swap_id']);
        $this->assertSame(4, $row['stars']);
    }

    // grāmatu tabā redz īpašnieku, apmaiņu skaitu, vērtējumu un pārbaudes iemeslu
    public function test_books_show_owner_swaps_rating_and_review_reason(): void
    {
        $anna = User::factory()->create(['name' => 'Anna']);
        $janis = User::factory()->create(['name' => 'Jānis']);
        $done = $this->makeSwap($anna, $janis);
        $this->actingAs($janis)->patchJson("/api/swap-requests/{$done->id}/accept")->assertOk();
        $this->actingAs($anna)->postJson('/api/ratings', ['swap_request_id' => $done->id, 'stars' => 5])->assertCreated();

        // Jāņa grāmata tagad pie Annas, un par to ir vēl viens gaidošs pieprasījums
        $book = $done->wantedBook->fresh();
        $book->update(['status' => 'Pending']);
        $liga = User::factory()->create();
        SwapRequest::create([
            'requester_id' => $liga->id,
            'owner_id' => $anna->id,
            'offered_book_id' => Book::factory()->create(['user_id' => $liga->id])->id,
            'wanted_book_id' => $book->id,
            'status' => 'pending',
        ]);

        $token = $this->adminToken();
        $other = Book::factory()->create(['user_id' => $janis->id]);
        $this->as($token)->patchJson("/api/admin/books/{$other->id}/review", ['reason' => 'Dīvains nosaukums'])->assertOk();

        $rows = collect($this->as($token)->getJson('/api/admin/books')->assertOk()->json())->keyBy('id');

        $this->assertSame('Anna', $rows[$book->id]['owner']['name']);
        $this->assertSame(1, $rows[$book->id]['swaps_accepted']);
        $this->assertSame(1, $rows[$book->id]['swaps_pending']);
        $this->assertEquals(5, $rows[$book->id]['rating_avg']);
        $this->assertSame('Dīvains nosaukums', $rows[$other->id]['review_reason']);
        $this->assertNull($rows[$book->id]['review_reason']);
    }

    // dzēsta konta apmaiņa - redzams, ka slēgta konta dēļ un lietotājs atzīmēts kā dzēsts
    public function test_account_deletion_is_recorded(): void
    {
        $anna = User::factory()->create();
        $janis = User::factory()->create();
        $swap = $this->makeSwap($anna, $janis);

        $this->actingAs($anna)->deleteJson('/api/profile', ['password' => 'password'])->assertSuccessful();

        $row = $this->row($this->adminToken(), $swap->id);
        $this->assertSame('deleted', $row['closed_by']);
        $this->assertTrue($row['requester']['deleted']);
    }
}
