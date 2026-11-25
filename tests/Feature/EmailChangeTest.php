<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ConfirmNewEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

// e-pasts mainās tikai pēc saites jaunajā e-pastā
// un paroli nevar minēt bezgalīgi (profils, 2FA izslēgšana, konta dzēšana)
class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private function link(User $user, string $email, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('email.confirm', now()->addMinutes($minutes), ['user' => $user->id, 'email' => $email]);
    }

    public function test_new_email_gets_confirmation_link_and_old_stays(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'vecais@example.com']);

        $this->actingAs($user)->patchJson('/api/profile', [
            'name' => $user->name,
            'email' => 'jaunais@example.com',
            'current_password' => 'password',
        ])->assertOk()->assertJsonPath('pending_email', 'jaunais@example.com');

        $this->assertSame('vecais@example.com', $user->fresh()->email);

        // saite aiziet uz jauno adresi, nevis veco
        Notification::assertSentTo(new AnonymousNotifiable, ConfirmNewEmail::class,
            fn($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'jaunais@example.com');
    }

    public function test_link_changes_the_email(): void
    {
        $user = User::factory()->create(['email' => 'vecais@example.com']);
        $user->forceFill(['pending_email' => 'jaunais@example.com'])->save();

        $this->get($this->link($user, 'jaunais@example.com'))->assertOk()->assertSee('Email confirmed');

        $user->refresh();
        $this->assertSame('jaunais@example.com', $user->email);
        $this->assertNull($user->pending_email);

        // otrreiz saite vairs neder
        $this->get($this->link($user, 'jaunais@example.com'))->assertSee('not valid anymore');
    }

    public function test_changed_or_expired_link_does_not_work(): void
    {
        $user = User::factory()->create(['email' => 'vecais@example.com']);
        $user->forceFill(['pending_email' => 'jaunais@example.com'])->save();

        // samainīts e-pasts saitē - paraksts neder
        $fake = str_replace('jaunais%40example.com', 'svesais%40example.com', $this->link($user, 'jaunais@example.com'));
        $this->get($fake)->assertForbidden();

        // saite beigusies
        $this->get($this->link($user, 'jaunais@example.com', -1))->assertForbidden();

        $this->assertSame('vecais@example.com', $user->fresh()->email);
    }

    public function test_old_link_does_not_work_after_asking_for_another_email(): void
    {
        $user = User::factory()->create(['email' => 'vecais@example.com']);
        $user->forceFill(['pending_email' => 'otrais@example.com'])->save();

        $this->get($this->link($user, 'pirmais@example.com'))->assertSee('not valid anymore');
        $this->assertSame('vecais@example.com', $user->fresh()->email);
    }

    public function test_cannot_confirm_email_that_someone_took_meanwhile(): void
    {
        $user = User::factory()->create(['email' => 'vecais@example.com']);
        $user->forceFill(['pending_email' => 'jaunais@example.com'])->save();
        User::factory()->create(['email' => 'jaunais@example.com']);

        $this->get($this->link($user, 'jaunais@example.com'))->assertSee('already used');
        $this->assertSame('vecais@example.com', $user->fresh()->email);
    }

    public function test_password_guessing_is_locked_after_5_tries(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->patchJson('/api/profile', [
                'name' => $user->name,
                'email' => 'cits@example.com',
                'current_password' => 'nepareiza' . $i,
            ])->assertStatus(422);
        }

        // tagad pat pareizā parole nelīdz, un limits kopīgs ar 2FA izslēgšanu un konta dzēšanu
        $this->actingAs($user)->patchJson('/api/profile', [
            'name' => $user->name,
            'email' => 'cits@example.com',
            'current_password' => 'password',
        ])->assertStatus(429)->assertJsonPath('throttled', true);
        $this->actingAs($user)->postJson('/api/two-factor/disable', ['password' => 'password'])->assertStatus(429);
        $this->actingAs($user)->deleteJson('/api/profile', ['password' => 'password'])->assertStatus(429);

        $this->assertNull($user->fresh()->pending_email);
    }

    public function test_name_change_does_not_need_password_or_count_as_try(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 7; $i++) {
            $this->actingAs($user)->patchJson('/api/profile', [
                'name' => 'Vārds ' . $i,
                'email' => $user->email,
            ])->assertOk();
        }
    }
}
