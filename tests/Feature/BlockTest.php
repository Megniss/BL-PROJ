<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_auth(): void
    {
        $user = User::factory()->create();
        $this->postJson("/api/blocks/{$user->id}")->assertUnauthorized();
    }

    public function test_can_block_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)
             ->postJson("/api/blocks/{$userB->id}")
             ->assertOk()
             ->assertJsonPath('blocked', true);

        $this->assertDatabaseHas('blocks', [
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);
    }

    public function test_cannot_block_yourself(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
             ->postJson("/api/blocks/{$user->id}")
             ->assertStatus(422);
    }

    public function test_can_unblock_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Block::create(['blocker_id' => $userA->id, 'blocked_id' => $userB->id]);

        $this->actingAs($userA)
             ->deleteJson("/api/blocks/{$userB->id}")
             ->assertOk()
             ->assertJsonPath('blocked', false);

        $this->assertDatabaseMissing('blocks', [
            'blocker_id' => $userA->id,
            'blocked_id' => $userB->id,
        ]);
    }

    public function test_can_list_blocked_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        Block::create(['blocker_id' => $userA->id, 'blocked_id' => $userB->id]);
        Block::create(['blocker_id' => $userA->id, 'blocked_id' => $userC->id]);

        $this->actingAs($userA)->getJson('/api/blocks')
             ->assertOk()
             ->assertJsonCount(2);
    }

    public function test_blocking_twice_doesnt_duplicate(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)->postJson("/api/blocks/{$userB->id}")->assertOk();
        $this->actingAs($userA)->postJson("/api/blocks/{$userB->id}")->assertOk();

        $this->assertDatabaseCount('blocks', 1);
    }

    public function test_blocked_user_cannot_send_message(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // A bloķēja B — nedrīkst rakstīt viens otram
        Block::create(['blocker_id' => $userA->id, 'blocked_id' => $userB->id]);

        $this->actingAs($userA)->postJson('/api/messages', [
            'to_user_id' => $userB->id,
            'body' => 'Čau!',
        ])->assertStatus(422);

        $this->actingAs($userB)->postJson('/api/messages', [
            'to_user_id' => $userA->id,
            'body' => 'Čau!',
        ])->assertStatus(422);
    }

    public function test_empty_block_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/blocks')
             ->assertOk()
             ->assertJsonCount(0);
    }
}
