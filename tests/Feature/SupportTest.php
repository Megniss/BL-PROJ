<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// atbalsta čats - bildes, jaunu pieteikumu paziņojums adminam
class SupportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        $user->forceFill(['admin_password' => 'admin12345678'])->save();

        return $user;
    }

    // tokens ar atvērtu admin paneli
    private function adminToken(User $admin): string
    {
        $token = $admin->createToken('auth_token');
        $token->accessToken->forceFill(['admin_until' => now()->addHour()])->save();

        return $token->plainTextToken;
    }

    private function as(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    // īsta 1x1 PNG, jo datorā nav GD fake bildēm
    private function image(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent('bilde.png', $png);
    }

    private function complaint(User $user): Complaint
    {
        $token = $user->createToken('t')->plainTextToken;

        $id = $this->as($token)->postJson('/api/complaints', ['subject' => 'Problēma', 'body' => 'Palīdziet'])
            ->assertCreated()
            ->json('id');

        return Complaint::find($id);
    }

    public function test_user_can_send_image_in_support_chat(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $complaint = $this->complaint($user);
        $token = $user->createToken('t')->plainTextToken;

        $res = $this->as($token)->post("/api/complaints/{$complaint->id}/messages", [
            'image' => $this->image(),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertNotNull($res->json('image_url'));
        $this->assertNotNull($res->json('created_at'));
        $this->assertCount(1, Storage::disk('local')->files('support'));
    }

    public function test_admin_can_reply_with_image(): void
    {
        Storage::fake('local');
        $complaint = $this->complaint(User::factory()->create());
        $token = $this->adminToken($this->admin());

        $this->as($token)->post("/api/admin/complaints/{$complaint->id}/messages", [
            'body' => 'Skaties šeit',
            'image' => $this->image(),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('from_admin', true);

        $this->assertCount(1, Storage::disk('local')->files('support'));
    }

    public function test_message_needs_text_or_image(): void
    {
        $user = User::factory()->create();
        $complaint = $this->complaint($user);
        $token = $user->createToken('t')->plainTextToken;

        $this->as($token)->postJson("/api/complaints/{$complaint->id}/messages", [])->assertStatus(422);
        $this->as($token)->post("/api/complaints/{$complaint->id}/messages", [
            'image' => UploadedFile::fake()->create('virus.exe', 10),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_admin_cannot_open_support_ticket_for_himself(): void
    {
        $token = $this->admin()->createToken('t')->plainTextToken;

        $this->as($token)->postJson('/api/complaints', ['subject' => 'Es', 'body' => 'Sev'])->assertForbidden();
        $this->assertSame(0, Complaint::count());
    }

    public function test_new_complaint_is_unread_until_admin_opens_it(): void
    {
        $user = User::factory()->create();
        $complaint = $this->complaint($user);
        $admin = $this->adminToken($this->admin());

        $this->as($admin)->getJson('/api/admin/complaints/unread-count')->assertJsonPath('count', 1);
        $this->as($admin)->getJson('/api/admin/complaints')->assertJsonPath('0.unread', true);

        // admins atver - vairs nav jauns
        $this->as($admin)->getJson("/api/admin/complaints/{$complaint->id}")->assertOk();
        $this->as($admin)->getJson('/api/admin/complaints/unread-count')->assertJsonPath('count', 0);

        // admina atbilde neko nemaina
        $this->as($admin)->postJson("/api/admin/complaints/{$complaint->id}/messages", ['body' => 'Sveiki'])->assertCreated();
        $this->as($admin)->getJson('/api/admin/complaints/unread-count')->assertJsonPath('count', 0);

        // lietotāja jauna ziņa - atkal jauns
        $this->as($user->createToken('t')->plainTextToken)
            ->postJson("/api/complaints/{$complaint->id}/messages", ['body' => 'Vēl jautājums'])
            ->assertCreated();
        $this->as($admin)->getJson('/api/admin/complaints/unread-count')->assertJsonPath('count', 1);
    }

    public function test_unread_count_needs_admin_panel(): void
    {
        $token = $this->admin()->createToken('t')->plainTextToken;

        $this->as($token)->getJson('/api/admin/complaints/unread-count')->assertForbidden();
    }

    public function test_deleting_account_removes_support_images(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['password' => 'Password1!']);
        $complaint = $this->complaint($user);
        $token = $user->createToken('t')->plainTextToken;

        $this->as($token)->post("/api/complaints/{$complaint->id}/messages", [
            'image' => $this->image(),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->as($token)->deleteJson('/api/profile', ['password' => 'Password1!'])->assertNoContent();

        $this->assertCount(0, Storage::disk('local')->files('support'));
        $this->assertTrue($complaint->messages()->get()->last()->image_removed);
    }
}
