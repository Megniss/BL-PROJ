<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\SwapRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_can_get_own_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertOk()
                 ->assertJsonStructure(['id', 'name', 'email', 'joined', 'books', 'swaps'])
                 ->assertJsonPath('email', $user->email);
    }

    public function test_profile_books_count_is_accurate(): void
    {
        $user = User::factory()->create();
        Book::factory()->count(3)->create(['user_id' => $user->id]);

        $this->actingAs($user)->getJson('/api/profile')
             ->assertOk()
             ->assertJsonPath('books', 3);
    }

    public function test_can_update_name_and_email(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/profile', [
            'name'  => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertOk()
                 ->assertJsonPath('name', 'New Name')
                 ->assertJsonPath('email', 'new@example.com');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name', 'email' => 'new@example.com']);
    }

    public function test_cannot_use_another_users_email(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)->patchJson('/api/profile', [
            'name'  => $user->name,
            'email' => 'taken@example.com',
        ])->assertStatus(422);
    }

    public function test_can_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPassword1!')]);

        $this->actingAs($user)->patchJson('/api/profile', [
            'name'                     => $user->name,
            'email'                    => $user->email,
            'current_password'         => 'OldPassword1!',
            'new_password'             => 'NewPassword1!',
            'new_password_confirmation' => 'NewPassword1!',
        ])->assertOk();

        // Verify new password works
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'NewPassword1!'])->assertOk();
    }

    public function test_cannot_change_password_with_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('RealPassword1!')]);

        $this->actingAs($user)->patchJson('/api/profile', [
            'name'                     => $user->name,
            'email'                    => $user->email,
            'current_password'         => 'wrongpassword',
            'new_password'             => 'NewPassword1!',
            'new_password_confirmation' => 'NewPassword1!',
        ])->assertStatus(422);
    }

    public function test_can_get_swap_history(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $offered = Book::factory()->create(['user_id' => $user->id,  'status' => 'Available']);
        $wanted  = Book::factory()->create(['user_id' => $other->id, 'status' => 'Available']);

        SwapRequest::create([
            'requester_id'    => $user->id,
            'owner_id'        => $other->id,
            'offered_book_id' => $offered->id,
            'wanted_book_id'  => $wanted->id,
            'status'          => 'accepted',
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile/history');

        $response->assertOk()
                 ->assertJsonStructure(['data', 'total', 'per_page']);
    }

    public function test_swap_history_requires_authentication(): void
    {
        $this->getJson('/api/profile/history')->assertUnauthorized();
    }

    public function test_can_get_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/notifications')
             ->assertOk()
             ->assertJsonStructure(['data', 'total']);
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        // Create a database notification manually
        $user->notifications()->create([
            'id'   => \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\SwapAccepted',
            'data' => json_encode(['message' => 'test']),
        ]);

        $this->actingAs($user)->postJson('/api/notifications/read-all')
             ->assertOk();

        $this->assertCount(0, $user->fresh()->unreadNotifications);
    }
}