<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

// nozagtu sesiju jāvar atņemt: e-pasts tikai ar paroli,
// paroles maiņa izlogo citur, tokeni beidzas
class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_email_cannot_be_changed_without_password(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);
        $token = $user->createToken('t')->plainTextToken;

        $this->as($token)->patchJson('/api/profile', [
            'name' => $user->name,
            'email' => 'uzbrucejs@example.com',
        ])->assertStatus(422)->assertJsonPath('errors.current_password.0', 'Current password is incorrect.');

        $this->as($token)->patchJson('/api/profile', [
            'name' => $user->name,
            'email' => 'uzbrucejs@example.com',
            'current_password' => 'nepareiza',
        ])->assertStatus(422);

        $this->assertSame('anna@example.com', $user->fresh()->email);
    }

    public function test_name_can_be_changed_without_password(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->as($token)->patchJson('/api/profile', [
            'name' => 'Jauns Vārds',
            'email' => $user->email,
        ])->assertOk();

        $this->assertSame('Jauns Vārds', $user->fresh()->name);
    }

    public function test_password_change_logs_out_other_devices_but_not_this_one(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1!']);
        $thisDevice = $user->createToken('t')->plainTextToken;
        $stolen = $user->createToken('t')->plainTextToken;

        $this->as($thisDevice)->patchJson('/api/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'OldPassword1!',
            'new_password' => 'NewPassword1!',
            'new_password_confirmation' => 'NewPassword1!',
        ])->assertOk();

        $this->as($stolen)->getJson('/api/me')->assertUnauthorized();
        $this->as($thisDevice)->getJson('/api/me')->assertOk();
    }

    public function test_email_change_logs_out_other_devices(): void
    {
        $user = User::factory()->create();
        $thisDevice = $user->createToken('t')->plainTextToken;
        $other = $user->createToken('t')->plainTextToken;

        $this->as($thisDevice)->patchJson('/api/profile', [
            'name' => $user->name,
            'email' => 'jauns@example.com',
            'current_password' => 'password',
        ])->assertOk();

        $this->as($other)->getJson('/api/me')->assertUnauthorized();
        $this->as($thisDevice)->getJson('/api/me')->assertOk();
    }

    public function test_password_reset_logs_out_everywhere(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.com']);
        $stolen = $user->createToken('t')->plainTextToken;
        DB::table('trusted_devices')->insert([
            'user_id' => $user->id, 'token_hash' => 'x', 'expires_at' => now()->addMinutes(5),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson('/api/forgot-password', ['email' => 'anna@example.com']);
        $resetToken = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$resetToken) {
            $resetToken = $n->token;
            return true;
        });

        $this->postJson('/api/reset-password', [
            'token' => $resetToken,
            'email' => 'anna@example.com',
            'password' => 'JaunaParole1!',
            'password_confirmation' => 'JaunaParole1!',
        ])->assertOk();

        $this->as($stolen)->getJson('/api/me')->assertUnauthorized();
        $this->assertSame(0, DB::table('trusted_devices')->where('user_id', $user->id)->count());
    }

    public function test_token_expires_after_30_days(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->as($token)->getJson('/api/me')->assertOk();

        $this->travel(31)->days();
        $this->as($token)->getJson('/api/me')->assertUnauthorized();
    }
}
