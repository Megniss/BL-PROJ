<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_by_email_not_returned(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $res = $this->postJson('/api/forgot-password', ['email' => 'anna@example.com'])
            ->assertOk();

        // links nedrīkst būt atbildē
        $this->assertArrayNotHasKey('reset_url', $res->json());
        $this->assertStringNotContainsString('token', $res->getContent());

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_gets_same_answer(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'anna@example.com']);

        $known = $this->postJson('/api/forgot-password', ['email' => 'anna@example.com']);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'nav@example.com']);

        $unknown->assertOk();
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_password_can_be_reset_with_emailed_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'anna@example.com']);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;
            return true;
        });

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'anna@example.com',
            'password' => 'JaunaParole1!',
            'password_confirmation' => 'JaunaParole1!',
        ])->assertOk();

        $this->assertTrue(Hash::check('JaunaParole1!', $user->fresh()->password));
    }

    public function test_wrong_token_is_rejected(): void
    {
        User::factory()->create(['email' => 'anna@example.com']);

        $this->postJson('/api/reset-password', [
            'token' => 'nepareizs',
            'email' => 'anna@example.com',
            'password' => 'JaunaParole1!',
            'password_confirmation' => 'JaunaParole1!',
        ])->assertStatus(422);
    }
}
