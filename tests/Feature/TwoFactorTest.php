<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TwoFactorCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function userWith2fa(): User
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);
        $user->forceFill(['two_factor_enabled' => true, 'two_factor_prompted' => true])->save();

        return $user;
    }

    // pēdējais uz e-pastu nosūtītais kods
    private function lastCode(User $user): string
    {
        $code = null;
        Notification::assertSentTo($user, TwoFactorCode::class, function ($n) use (&$code) {
            $code = $n->code;
            return true;
        });

        return $code;
    }

    private function login(array $extra = [])
    {
        return $this->postJson('/api/login', ['email' => 'anna@example.com', 'password' => 'password'] + $extra);
    }

    public function test_login_without_2fa_gives_token_directly(): void
    {
        User::factory()->create(['email' => 'anna@example.com']);

        $this->login()->assertOk()->assertJsonStructure(['user', 'token']);
        Notification::assertNothingSent();
    }

    public function test_login_with_2fa_requires_emailed_code(): void
    {
        $user = $this->userWith2fa();

        $response = $this->login()->assertOk()->assertJsonPath('two_factor', true)->assertJsonMissing(['token']);
        $challenge = $response->json('challenge');

        $this->postJson('/api/login/verify', ['challenge' => $challenge, 'code' => $this->lastCode($user)])
             ->assertOk()
             ->assertJsonStructure(['user', 'token'])
             ->assertJsonPath('device_token', null);
    }

    public function test_wrong_code_is_rejected_and_limited(): void
    {
        $this->userWith2fa();
        $challenge = $this->login()->json('challenge');

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/login/verify', ['challenge' => $challenge, 'code' => '000000x'])
                 ->assertStatus(422)->assertJsonPath('error', 'invalid');
        }

        // piektā kļūda — jāsāk no jauna
        $this->postJson('/api/login/verify', ['challenge' => $challenge, 'code' => '000000x'])
             ->assertJsonPath('error', 'expired');
    }

    public function test_code_expires_after_ten_minutes(): void
    {
        $user = $this->userWith2fa();
        $challenge = $this->login()->json('challenge');
        $code = $this->lastCode($user);

        $this->travel(11)->minutes();

        $this->postJson('/api/login/verify', ['challenge' => $challenge, 'code' => $code])
             ->assertStatus(422)->assertJsonPath('error', 'expired');
    }

    public function test_code_can_be_used_only_once(): void
    {
        $user = $this->userWith2fa();
        $challenge = $this->login()->json('challenge');
        $code = $this->lastCode($user);

        $this->postJson('/api/login/verify', ['challenge' => $challenge, 'code' => $code])->assertOk();
        $this->postJson('/api/login/verify', ['challenge' => $challenge, 'code' => $code])->assertStatus(422);
    }

    public function test_remembered_device_skips_code_for_ten_minutes(): void
    {
        $user = $this->userWith2fa();
        $challenge = $this->login()->json('challenge');

        $device = $this->postJson('/api/login/verify', [
            'challenge' => $challenge, 'code' => $this->lastCode($user), 'remember' => true,
        ])->json('device_token');

        $this->assertNotEmpty($device);

        $this->travel(9)->minutes();
        $this->login(['device_token' => $device])->assertJsonStructure(['token'])->assertJsonMissing(['two_factor' => true]);

        $this->travel(2)->minutes();
        $this->login(['device_token' => $device])->assertJsonPath('two_factor', true);
    }

    public function test_resend_sends_new_code(): void
    {
        $user = $this->userWith2fa();
        $challenge = $this->login()->json('challenge');

        $this->postJson('/api/login/resend', ['challenge' => $challenge])->assertOk();

        Notification::assertSentToTimes($user, TwoFactorCode::class, 2);
    }

    public function test_enable_requires_code_from_email(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $challenge = $this->postJson('/api/two-factor/send')->assertOk()->json('challenge');

        $this->postJson('/api/two-factor/enable', ['challenge' => $challenge, 'code' => 'wrong'])->assertStatus(422);
        $this->assertFalse($user->fresh()->two_factor_enabled);

        $this->postJson('/api/two-factor/enable', ['challenge' => $challenge, 'code' => $this->lastCode($user)])->assertOk();

        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
        $this->assertTrue($user->two_factor_prompted);
    }

    public function test_enable_code_cannot_be_used_to_login(): void
    {
        $user = $this->userWith2fa();
        $this->actingAs($user);
        $challenge = $this->postJson('/api/two-factor/send')->json('challenge');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login/verify', ['challenge' => $challenge, 'code' => $this->lastCode($user)])
             ->assertStatus(422);
    }

    public function test_disable_requires_password_and_forgets_devices(): void
    {
        $user = $this->userWith2fa();
        $challenge = $this->login()->json('challenge');
        $device = $this->postJson('/api/login/verify', [
            'challenge' => $challenge, 'code' => $this->lastCode($user), 'remember' => true,
        ])->json('device_token');

        $this->actingAs($user);
        $this->postJson('/api/two-factor/disable', ['password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/two-factor/disable', ['password' => 'password'])->assertOk();

        $this->assertFalse($user->fresh()->two_factor_enabled);
        $this->assertDatabaseMissing('trusted_devices', ['user_id' => $user->id]);
        $this->assertNotEmpty($device);
    }

    public function test_prompt_can_be_dismissed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/two-factor/dismiss')->assertOk();

        $this->assertTrue($user->fresh()->two_factor_prompted);
        $this->assertFalse($user->fresh()->two_factor_enabled);
    }

    public function test_blocked_user_cannot_finish_login(): void
    {
        $user = $this->userWith2fa();
        $challenge = $this->login()->json('challenge');
        $user->update(['is_blocked' => true]);

        $this->postJson('/api/login/verify', ['challenge' => $challenge, 'code' => $this->lastCode($user)])
             ->assertForbidden();
    }
}
