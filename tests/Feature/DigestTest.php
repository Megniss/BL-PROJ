<?php

namespace Tests\Feature;

use App\Mail\DigestEmail;
use App\Models\Block;
use App\Models\Book;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Support\Digest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// e-pasti pa veidiem: piekrišana katram atsevišķi, saturs, atrakstīšanās
class DigestTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(array $types, array $extra = []): User
    {
        $user = User::factory()->create();
        if ($extra) {
            $user->forceFill($extra)->save();
        }
        foreach ($types as $type) {
            $user->emailSubscriptions()->create(['type' => $type]);
        }

        return $user;
    }

    // viena pieņemta maiņa, lai e-pastā ir ko rādīt
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

    private function mail(User $user, string $type): DigestEmail
    {
        $global = Digest::global();

        return new DigestEmail($user, $type, Digest::items($type, $user, $global), $global['week']);
    }

    public function test_all_emails_are_off_by_default_and_each_can_be_turned_on(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/profile')
            ->assertJsonPath('emails', ['nearby' => false, 'popular' => false, 'authors' => false, 'rated' => false]);

        $this->actingAs($user)->patchJson('/api/profile/emails', ['popular' => true, 'rated' => true])
            ->assertOk()
            ->assertJson(['nearby' => false, 'popular' => true, 'authors' => false, 'rated' => true]);

        // izslēdz vienu, otrs paliek
        $this->actingAs($user)->patchJson('/api/profile/emails', ['popular' => false])
            ->assertJson(['popular' => false, 'rated' => true]);

        $this->assertSame(['rated'], $user->emailSubscriptions()->pluck('type')->all());
    }

    public function test_unknown_email_type_is_ignored(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/profile/emails', ['spam' => true])->assertOk();

        $this->assertSame(0, $user->emailSubscriptions()->count());
    }

    public function test_each_type_is_sent_only_to_its_subscribers(): void
    {
        Mail::fake();
        $this->makeSwap();

        $popular = $this->subscriber(['popular']);
        $authors = $this->subscriber(['authors']);
        $none = User::factory()->create();
        $blocked = $this->subscriber(['popular'], ['is_blocked' => true]);
        $deleted = $this->subscriber(['popular'], ['deleted_at' => now()]);

        $this->artisan('bookloop:emails popular')->assertSuccessful();

        Mail::assertSent(DigestEmail::class, 1);
        Mail::assertSent(DigestEmail::class, fn($m) => $m->hasTo($popular->email) && $m->type === 'popular');
        foreach ([$authors, $none, $blocked, $deleted] as $u) {
            Mail::assertNotSent(DigestEmail::class, fn($m) => $m->hasTo($u->email));
        }
    }

    public function test_without_type_every_type_goes_as_separate_email(): void
    {
        Mail::fake();
        $this->makeSwap();
        $user = $this->subscriber(['popular', 'authors']);

        $this->artisan('bookloop:emails')->assertSuccessful();

        // divi atsevišķi e-pasti, nevis viens kopā
        Mail::assertSent(DigestEmail::class, 2);
        Mail::assertSent(DigestEmail::class, fn($m) => $m->type === 'popular' && str_contains($m->envelope()->subject, 'Most swapped'));
        Mail::assertSent(DigestEmail::class, fn($m) => $m->type === 'authors' && str_contains($m->envelope()->subject, 'authors'));
        $this->assertSame(2, $user->emailSubscriptions()->whereNotNull('sent_at')->count());
    }

    public function test_not_sent_twice_in_one_week(): void
    {
        Mail::fake();
        $this->makeSwap();
        $this->subscriber(['popular']);

        $this->artisan('bookloop:emails popular');
        $this->artisan('bookloop:emails popular');
        Mail::assertSent(DigestEmail::class, 1);

        // --force sūta vēlreiz
        $this->artisan('bookloop:emails popular --force');
        Mail::assertSent(DigestEmail::class, 2);
    }

    public function test_wrong_type_fails(): void
    {
        $this->artisan('bookloop:emails spam')->assertFailed();
    }

    public function test_empty_email_is_not_sent(): void
    {
        Mail::fake();
        // nav ne maiņu, ne vērtējumu, nav atrašanās vietas
        $this->subscriber(['nearby', 'popular', 'authors', 'rated']);

        $this->artisan('bookloop:emails')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_popular_books_and_authors(): void
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
        $user = $this->subscriber(['nearby'], ['latitude' => 56.95, 'longitude' => 24.11]);
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
        $user = $this->subscriber(['popular']);
        $mail = $this->mail($user, 'popular');

        $html = $mail->render();
        $this->assertStringContainsString('/email/unsubscribe/' . $user->id . '/popular?signature=', $html);
        $this->assertStringContainsString('Kaija', $html);
        // gmail apgriež e-pastus virs ~102 KB
        $this->assertLessThan(102 * 1024, strlen($html));

        $headers = $mail->headers()->text;
        $this->assertStringContainsString('/api/email/unsubscribe/' . $user->id . '/popular', $headers['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
    }

    // kartītes (3 blakus uz datora), outlook classic ar slēptu tabulu
    public function test_books_are_cards_with_outlook_fallback(): void
    {
        $this->makeSwap();
        $user = $this->subscriber(['popular']);

        $html = $this->mail($user, 'popular')->render();

        $this->assertStringContainsString('class="card"', $html);
        $this->assertStringContainsString('<!--[if mso]>', $html);
        // nedēļas skaitļi ir tikai popular e-pastā
        $this->assertStringContainsString('class="stat"', $html);
        $this->assertStringNotContainsString('class="stat"', $this->mail($user, 'authors')->render());
        // markdown nedrīkst html pārvērst par tekstu vai kodu
        $this->assertStringNotContainsString('&lt;div', $html);
        $this->assertStringNotContainsString('<code>', $html);
    }

    public function test_unsubscribe_removes_only_that_type(): void
    {
        $user = $this->subscriber(['popular', 'rated']);

        $this->get(DigestEmail::unsubscribeUrl($user, 'popular'))->assertOk()->assertSee('Most swapped books');

        $this->assertSame(['rated'], $user->emailSubscriptions()->pluck('type')->all());
    }

    public function test_one_click_unsubscribe_post(): void
    {
        $user = $this->subscriber(['rated', 'authors']);

        $this->post(DigestEmail::unsubscribeUrl($user, 'rated', true))->assertNoContent();

        $this->assertSame(['authors'], $user->emailSubscriptions()->pluck('type')->all());
    }

    public function test_unsubscribe_without_signature_is_rejected(): void
    {
        $user = $this->subscriber(['popular', 'rated']);
        $other = $this->subscriber(['popular']);

        $this->get('/email/unsubscribe/' . $user->id . '/popular')->assertForbidden();
        $this->postJson('/api/email/unsubscribe/' . $user->id . '/popular')->assertForbidden();

        // ar cita lietotāja parakstu arī nevar
        $url = str_replace('/unsubscribe/' . $other->id . '/', '/unsubscribe/' . $user->id . '/', DigestEmail::unsubscribeUrl($other, 'popular'));
        $this->get($url)->assertForbidden();

        // ar cita veida parakstu arī nevar
        $url = str_replace('/popular?', '/rated?', DigestEmail::unsubscribeUrl($user, 'popular'));
        $this->get($url)->assertForbidden();

        $this->assertSame(2, $user->emailSubscriptions()->count());
    }

    public function test_deleting_account_removes_subscriptions(): void
    {
        $user = $this->subscriber(['popular', 'rated']);
        $user->forceFill(['password' => 'Password1!'])->save();

        $this->actingAs($user)->deleteJson('/api/profile', ['password' => 'Password1!'])->assertSuccessful();

        $this->assertSame(0, $user->emailSubscriptions()->count());
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
        $this->makeSwap('[y](http://evil.test)', '*z*');
        $digest = $this->mail($user, 'popular')->render();
        $this->assertStringNotContainsString('href="http://evil.test"', $digest);
    }
}
