<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/messages')->assertUnauthorized();
    }

    public function test_can_list_conversations(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Message::create(['from_user_id' => $userA->id, 'to_user_id' => $userB->id, 'body' => 'Hello']);

        $response = $this->actingAs($userA)->getJson('/api/messages');

        $response->assertOk()
                 ->assertJsonCount(1)
                 ->assertJsonPath('0.user.id', $userB->id)
                 ->assertJsonPath('0.last_message.body', 'Hello');
    }

    public function test_conversations_list_is_empty_when_no_messages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/messages')
             ->assertOk()
             ->assertJsonCount(0);
    }

    public function test_can_get_message_thread(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Message::create(['from_user_id' => $userA->id, 'to_user_id' => $userB->id, 'body' => 'Hey']);
        Message::create(['from_user_id' => $userB->id, 'to_user_id' => $userA->id, 'body' => 'Hi back']);

        $response = $this->actingAs($userA)->getJson("/api/messages/{$userB->id}");

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_fetching_thread_marks_incoming_messages_as_read(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $msg = Message::create([
            'from_user_id' => $userB->id,
            'to_user_id'   => $userA->id,
            'body'         => 'Unread message',
            'read_at'      => null,
        ]);

        $this->actingAs($userA)->getJson("/api/messages/{$userB->id}")->assertOk();

        $this->assertNotNull($msg->fresh()->read_at);
    }

    public function test_can_send_message(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $response = $this->actingAs($sender)->postJson('/api/messages', [
            'to_user_id' => $recipient->id,
            'body'       => 'Hello there!',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('messages', [
            'from_user_id' => $sender->id,
            'to_user_id'   => $recipient->id,
            'body'         => 'Hello there!',
        ]);
    }

    public function test_cannot_message_yourself(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/messages', [
            'to_user_id' => $user->id,
            'body'       => 'Talking to myself',
        ])->assertStatus(422);
    }

    public function test_message_body_is_required(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $this->actingAs($sender)->postJson('/api/messages', [
            'to_user_id' => $recipient->id,
            'body'       => '',
        ])->assertStatus(422);
    }

    public function test_message_body_max_length(): void
    {
        $sender    = User::factory()->create();
        $recipient = User::factory()->create();

        $this->actingAs($sender)->postJson('/api/messages', [
            'to_user_id' => $recipient->id,
            'body'       => str_repeat('a', 2001),
        ])->assertStatus(422);
    }

    public function test_unread_count_returns_zero_initially(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/messages/unread-count')
             ->assertOk()
             ->assertJsonPath('count', 0);
    }

    public function test_unread_count_reflects_unread_messages(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Message::create(['from_user_id' => $userB->id, 'to_user_id' => $userA->id, 'body' => 'Msg 1', 'read_at' => null]);
        Message::create(['from_user_id' => $userB->id, 'to_user_id' => $userA->id, 'body' => 'Msg 2', 'read_at' => null]);

        $this->actingAs($userA)->getJson('/api/messages/unread-count')
             ->assertOk()
             ->assertJsonPath('count', 2);
    }
}
