<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attrs = []): User
    {
        $user = User::factory()->create(['is_admin' => true] + $attrs);
        $user->forceFill(['admin_password' => 'admin12345678'])->save();

        return $user;
    }

    // tokens ar vai bez atvērta admin paneļa
    private function token(User $user, bool $unlocked = false): string
    {
        $token = $user->createToken('auth_token');

        if ($unlocked) {
            $token->accessToken->forceFill(['admin_until' => now()->addHour()])->save();
        }

        return $token->plainTextToken;
    }

    // starp pieprasījumiem jāaizmirst iepriekšējais lietotājs
    private function as(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_regular_user_cannot_unlock(): void
    {
        $user = User::factory()->create();

        $this->as($this->token($user))
             ->postJson('/api/admin/unlock', ['admin_password' => 'admin12345678'])
             ->assertForbidden();
    }

    public function test_admin_needs_admin_password_for_panel(): void
    {
        $token = $this->token($this->admin());

        $this->as($token)->getJson('/api/admin/users')
             ->assertForbidden()
             ->assertJsonPath('admin_locked', true);
    }

    public function test_wrong_admin_password_is_logged(): void
    {
        $admin = $this->admin();

        $this->as($this->token($admin))
             ->postJson('/api/admin/unlock', ['admin_password' => 'wrong-password'])
             ->assertStatus(422);

        $this->assertDatabaseHas('admin_logs', ['admin_id' => $admin->id, 'action' => 'admin_unlock_failed']);
    }

    public function test_correct_admin_password_opens_panel(): void
    {
        $token = $this->token($this->admin());

        $this->as($token)->postJson('/api/admin/unlock', ['admin_password' => 'admin12345678'])
             ->assertOk()
             ->assertJsonPath('unlocked', true);

        $this->as($token)->getJson('/api/admin/users')->assertOk();
    }

    public function test_unlock_is_throttled(): void
    {
        $token = $this->token($this->admin());

        for ($i = 0; $i < 5; $i++) {
            $this->as($token)->postJson('/api/admin/unlock', ['admin_password' => 'wrong-password']);
        }

        $this->as($token)->postJson('/api/admin/unlock', ['admin_password' => 'admin12345678'])
             ->assertStatus(429);
    }

    public function test_unlock_expires(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('auth_token');
        $token->accessToken->forceFill(['admin_until' => now()->subMinute()])->save();

        $this->as($token->plainTextToken)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_lock_closes_panel(): void
    {
        $token = $this->token($this->admin(), true);

        $this->as($token)->postJson('/api/admin/lock')->assertOk();
        $this->as($token)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_new_admin_must_change_temp_password(): void
    {
        $boss = $this->admin();
        $user = User::factory()->create();

        $this->as($this->token($boss, true))
             ->patchJson("/api/admin/users/{$user->id}/make-admin", ['admin_password' => 'temporary1234'])
             ->assertOk();

        $user->refresh();
        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->admin_password_change);

        $token = $this->token($user);
        $this->as($token)->postJson('/api/admin/unlock', ['admin_password' => 'temporary1234'])
             ->assertJsonPath('must_change', true);

        $this->as($token)->getJson('/api/admin/users')->assertForbidden()->assertJsonPath('must_change', true);

        // drīkst uzlikt tādu pašu paroli kā citiem adminiem
        $this->as($token)->patchJson('/api/admin/password', [
            'new_password' => 'admin12345678',
            'new_password_confirmation' => 'admin12345678',
        ])->assertOk();

        $this->as($token)->getJson('/api/admin/users')->assertOk();
    }

    public function test_temp_password_must_be_long(): void
    {
        $user = User::factory()->create();

        $this->as($this->token($this->admin(), true))
             ->patchJson("/api/admin/users/{$user->id}/make-admin", ['admin_password' => 'short'])
             ->assertStatus(422);

        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_change_password_requires_current(): void
    {
        $token = $this->token($this->admin(), true);

        $this->as($token)->patchJson('/api/admin/password', [
            'current_password' => 'wrong-password',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ])->assertStatus(422);

        $this->as($token)->patchJson('/api/admin/password', [
            'current_password' => 'admin12345678',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ])->assertOk();
    }

    public function test_remove_admin_clears_password_and_access(): void
    {
        $boss = $this->admin();
        $other = $this->admin();
        $otherToken = $this->token($other, true);

        $this->as($this->token($boss, true))
             ->patchJson("/api/admin/users/{$other->id}/remove-admin")
             ->assertOk();

        $other->refresh();
        $this->assertFalse($other->is_admin);
        $this->assertNull($other->admin_password);
        $this->as($otherToken)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_admin_sees_only_own_complaints_on_user_side(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $complaint = Complaint::create(['user_id' => $user->id, 'subject' => 'Problēma', 'status' => 'open']);
        ComplaintMessage::create(['complaint_id' => $complaint->id, 'sender_id' => $user->id, 'body' => 'Palīdziet']);

        $token = $this->token($admin, true);

        $this->as($token)->getJson('/api/complaints')->assertOk()->assertJsonCount(0);
        $this->as($token)->getJson("/api/complaints/{$complaint->id}")->assertForbidden();
        $this->as($token)->getJson('/api/admin/complaints')->assertOk()->assertJsonCount(1);

        $this->as($token)->postJson("/api/admin/complaints/{$complaint->id}/messages", ['body' => 'Sveiki'])
             ->assertCreated()
             ->assertJsonPath('from_admin', true);
    }

    public function test_admin_complaints_need_admin_password(): void
    {
        $token = $this->token($this->admin());

        $this->as($token)->getJson('/api/admin/complaints')->assertForbidden();
    }
}
