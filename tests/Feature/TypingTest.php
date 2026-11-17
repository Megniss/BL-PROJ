<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// "raksta..." burbuļi abos čatos
class TypingTest extends TestCase
{
    use RefreshDatabase;

    private function typing(User $who, string $chat)
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($who)->getJson('/api/typing?chat=' . $chat)->json('typing');
    }

    private function startTyping(User $who, string $chat)
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($who)->postJson('/api/typing', ['chat' => $chat]);
    }

    public function test_other_user_sees_typing(): void
    {
        $anna = User::factory()->create();
        $janis = User::factory()->create();

        $this->assertFalse($this->typing($janis, "user:{$anna->id}"));

        $this->startTyping($anna, "user:{$janis->id}")->assertNoContent();

        $this->assertTrue($this->typing($janis, "user:{$anna->id}"));
        // pats sevi neredz
        $this->assertFalse($this->typing($anna, "user:{$janis->id}"));
    }

    public function test_typing_expires_after_few_seconds(): void
    {
        $anna = User::factory()->create();
        $janis = User::factory()->create();

        $this->startTyping($anna, "user:{$janis->id}");
        $this->travel(6)->seconds();

        $this->assertFalse($this->typing($janis, "user:{$anna->id}"));
    }

    public function test_sending_message_stops_typing(): void
    {
        $anna = User::factory()->create();
        $janis = User::factory()->create();

        $this->startTyping($anna, "user:{$janis->id}");
        $this->actingAs($anna)->postJson('/api/messages', ['to_user_id' => $janis->id, 'body' => 'Sveiki'])->assertCreated();

        $this->assertFalse($this->typing($janis, "user:{$anna->id}"));
    }

    public function test_blocked_user_cannot_send_typing(): void
    {
        $anna = User::factory()->create();
        $janis = User::factory()->create();
        Block::create(['blocker_id' => $janis->id, 'blocked_id' => $anna->id]);

        $this->startTyping($anna, "user:{$janis->id}")->assertForbidden();
        $this->assertFalse($this->typing($janis, "user:{$anna->id}"));
    }

    public function test_support_typing_works_both_ways(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $complaint = Complaint::create(['user_id' => $user->id, 'subject' => 'Problēma', 'status' => 'open']);

        $this->startTyping($user, "support:{$complaint->id}")->assertNoContent();
        $this->assertTrue($this->typing($admin, "support:{$complaint->id}"));
        $this->assertFalse($this->typing($user, "support:{$complaint->id}"));

        $this->startTyping($admin, "support:{$complaint->id}")->assertNoContent();
        $this->assertTrue($this->typing($user, "support:{$complaint->id}"));
    }

    public function test_stranger_cannot_use_someone_elses_support_chat(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $complaint = Complaint::create(['user_id' => $user->id, 'subject' => 'Problēma', 'status' => 'open']);

        $this->startTyping($stranger, "support:{$complaint->id}")->assertForbidden();
        $this->startTyping($stranger, 'nekas:1')->assertForbidden();
    }
}
