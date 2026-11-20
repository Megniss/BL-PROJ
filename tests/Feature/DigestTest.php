<?php

namespace Tests\Feature;

use App\Mail\WeeklyDigest;
use App\Models\Block;
use App\Models\Book;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Support\Digest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// iknedēļas apkopojums: piekrišana, saturs, atrakstīšanās
class DigestTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(array $extra = []): User
    {
        $user = User::factory()->create();
        $user->forceFill(['digest_opt_in' => true, ...$extra])->save();

        return $user;
    }

    // viena pieņemta maiņa, lai apkopojumā ir ko rādīt
    private function makeSwap(string $title = 'Kaija', string $author = 'Čehovs'): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $wanted = Book::factory()->create(['user_id' => $owner->id, 'title' => $title, 'author' => $author, 'status' => 'Swapped']);
        $offered = Book::factory()->create(['user_id' => $requester->id, 'status' => 'Swapped']);

        SwapRequest::create([
            'requester_id' => $requester->id,
            'owner_id' => $owner->id,
            'offered_book_id' => $offered->id,
            'wanted_book_id' => $wanted->id,
            'status' => 'accepted',
        ]);
    }

    public function test_digest_is_off_by_default_and_can_be_turned_on(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/profile')->assertJsonPath('digest_opt_in', false);

        $this->actingAs($user)->patchJson('/api/profile/privacy', [
            'show_joined' => true,
            'show_swaps' => true,
            'show_swap_history' => true,
            'digest_opt_in' => true,
        ])->assertOk()->assertJsonPath('digest_opt_in', true);

        $this->assertTrue($user->fresh()->digest_opt_in);
    }

    public function test_sends_only_to_users_who_opted_in(): void
    {
        Mail::fake();
        $this->makeSwap();

        $yes = $this->subscriber();
        $no = User::factory()->create();
        $blocked = $this->subscriber(['is_blocked' => true]);
        $deleted = $this->subscriber(['deleted_at' => now()]);

        $this->artisan('bookloop:weekly-digest')->assertSuccessful();

        Mail::assertSent(WeeklyDigest::class, 1);
        Mail::assertSent(WeeklyDigest::class, fn($m) => $m->hasTo($yes->email));
        Mail::assertNotSent(WeeklyDigest::class, fn($m) => $m->hasTo($no->email) || $m->hasTo($blocked->email) || $m->hasTo($deleted->email));
        $this->assertNotNull($yes->fresh()->digest_sent_at);
    }

    public function test_not_sent_twice_in_one_week(): void
    {
        Mail::fake();
        $this->makeSwap();
        $this->subscriber();

        $this->artisan('bookloop:weekly-digest');
        $this->artisan('bookloop:weekly-digest');
        Mail::assertSent(WeeklyDigest::class, 1);

        // --force sūta vēlreiz
        $this->artisan('bookloop:weekly-digest --force');
        Mail::assertSent(WeeklyDigest::class, 2);
    }

    public function test_nothing_sent_when_there_is_nothing_to_show(): void
    {
        Mail::fake();
        $this->subscriber();

        $this->artisan('bookloop:weekly-digest')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_digest_contains_popular_books_and_authors(): void
    {
        $this->makeSwap('Kaija', 'Čehovs');
        $this->makeSwap('Kaija', 'Čehovs');
        $this->makeSwap('Fausts', 'Gēte');

        $global = Digest::global();

        $this->assertSame('Kaija', $global['popular'][0]['title']);
        $this->assertSame('2 swaps', $global['popular'][0]['badge']);
        $this->assertSame('Čehovs', $global['authors'][0]['title']);
    }

    public function test_removed_books_are_not_shown(): void
    {
        $this->makeSwap('Slikta', 'Kāds');
        Book::where('title', 'Slikta')->update(['status' => 'Removed']);

        $this->assertSame([], Digest::global()['popular']);
    }

    public function test_nearby_shows_new_books_close_by_but_not_far_own_or_blocked(): void
    {
        // Rīga
        $user = $this->subscriber(['latitude' => 56.95, 'longitude' => 24.11]);
        $near = User::factory()->create();
        $near->forceFill(['latitude' => 56.97, 'longitude' => 24.15])->save();
        $far = User::factory()->create();
        $far->forceFill(['latitude' => 56.51, 'longitude' => 21.01])->save(); // Liepāja
        $blocked = User::factory()->create();
        $blocked->forceFill(['latitude' => 56.95, 'longitude' => 24.12])->save();
        Block::create(['blocker_id' => $user->id, 'blocked_id' => $blocked->id]);

        Book::factory()->create(['user_id' => $near->id, 'title' => 'Tuvā']);
        Book::factory()->create(['user_id' => $far->id, 'title' => 'Tālā']);
        Book::factory()->create(['user_id' => $blocked->id, 'title' => 'Bloķētā']);
        Book::factory()->create(['user_id' => $user->id, 'title' => 'Mana']);
        $old = Book::factory()->create(['user_id' => $near->id, 'title' => 'Vecā']);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $titles = array_column(Digest::nearby($user), 'title');

        $this->assertSame(['Tuvā'], $titles);
    }

    public function test_email_has_unsubscribe_link_and_headers(): void
    {
        $this->makeSwap();
        $user = $this->subscriber();
        $mail = new WeeklyDigest($user, Digest::global(), Digest::nearby($user));

        $html = $mail->render();
        $this->assertStringContainsString('/email/unsubscribe/' . $user->id . '?signature=', $html);
        $this->assertStringContainsString('Kaija', $html);
        // gmail apgriež e-pastus virs ~102 KB
        $this->assertLessThan(102 * 1024, strlen($html));

        $headers = $mail->headers()->text;
        $this->assertStringContainsString('/api/email/unsubscribe/' . $user->id, $headers['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
    }

    public function test_unsubscribe_link_works_without_login(): void
    {
        $user = $this->subscriber();

        $this->get(WeeklyDigest::unsubscribeUrl($user))->assertOk()->assertSee('unsubscribed');

        $this->assertFalse($user->fresh()->digest_opt_in);
    }

    public function test_one_click_unsubscribe_post(): void
    {
        $user = $this->subscriber();

        $this->post(WeeklyDigest::unsubscribeUrl($user, true))->assertNoContent();

        $this->assertFalse($user->fresh()->digest_opt_in);
    }

    public function test_unsubscribe_without_signature_is_rejected(): void
    {
        $user = $this->subscriber();
        $other = $this->subscriber();

        $this->get('/email/unsubscribe/' . $user->id)->assertForbidden();
        $this->postJson('/api/email/unsubscribe/' . $user->id)->assertForbidden();

        // ar cita lietotāja parakstu arī nevar
        $url = str_replace('/unsubscribe/' . $other->id, '/unsubscribe/' . $user->id, WeeklyDigest::unsubscribeUrl($other));
        $this->get($url)->assertForbidden();

        $this->assertTrue($user->fresh()->digest_opt_in);
    }

    // lietotāja vārds ar markdown saiti nedrīkst kļūt par īstu saiti e-pastā
    public function test_user_text_cannot_inject_links_into_emails(): void
    {
        $sender = User::factory()->create(['name' => '[Click here](http://evil.test)']);
        $user = User::factory()->create();

        $html = (string) (new NewMessage($sender, '[win](http://evil.test) *now*'))->toMail($user)->render();

        $this->assertStringNotContainsString('href="http://evil.test"', $html);
        $this->assertStringContainsString('[Click here](http://evil.test)', $html);

        $user->forceFill(['name' => '[x](http://evil.test)'])->save();
        $this->makeSwap();
        $digest = (new WeeklyDigest($user, Digest::global(), []))->render();
        $this->assertStringNotContainsString('href="http://evil.test"', $digest);
    }
}
