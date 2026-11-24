<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Message;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Notifications\SwapUnavailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

// zvaniņš: skaits, saites, gaidošās apmaiņas, izlasīšana
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function addNotification(User $user, string $class, array $data, bool $read = false): void
    {
        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => $class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => $data,
            'read_at' => $read ? now() : null,
        ]);
    }

    // agrāk skaitīja tikai pirmos 15, tagad īsto skaitu
    public function test_unread_count_is_real_not_only_first_page(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 20; $i++) {
            $this->addNotification($user, SwapUnavailable::class, ['message' => 'x', 'book' => "Book $i"]);
        }
        $this->addNotification($user, SwapUnavailable::class, ['message' => 'x'], true);

        $res = $this->actingAs($user)->getJson('/api/notifications')->assertOk();

        $this->assertCount(15, $res->json('data'));
        $this->assertSame(20, $res->json('unread_count'));
        $this->assertNotNull($res->json('next_page_url'));
    }

    public function test_each_notification_has_kind_and_link(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create(['name' => 'Anna']);
        $this->addNotification($user, NewMessage::class, ['message' => 'x', 'sender_id' => $sender->id, 'sender_name' => 'Anna']);

        $n = $this->actingAs($user)->getJson('/api/notifications')->json('data.0');

        $this->assertSame('message', $n['kind']);
        $this->assertSame('/messages?userId=' . $sender->id . '&userName=Anna', $n['link']);
    }

    public function test_pending_swap_requests_come_with_notifications(): void
    {
        $owner = User::factory()->create();
        $requester = User::factory()->create();
        $wanted = Book::factory()->create(['user_id' => $owner->id]);
        $offered = Book::factory()->create(['user_id' => $requester->id]);
        SwapRequest::create([
            'requester_id' => $requester->id,
            'owner_id' => $owner->id,
            'offered_book_id' => $offered->id,
            'wanted_book_id' => $wanted->id,
            'status' => 'pending',
        ]);

        $res = $this->actingAs($owner)->getJson('/api/notifications');

        $this->assertCount(1, $res->json('pending_swaps'));
        $this->assertSame($wanted->title, $res->json('pending_swaps.0.wanted_book.title'));
        // pieprasītājam nekas nav jādara
        $this->assertCount(0, $this->actingAs($requester)->getJson('/api/notifications')->json('pending_swaps'));
    }

    // atverot sarunu, paziņojums par to zvaniņā vairs nav jauns
    public function test_opening_conversation_marks_its_notification_read(): void
    {
        $user = User::factory()->create();
        $anna = User::factory()->create();
        $peteris = User::factory()->create();
        Message::create(['from_user_id' => $anna->id, 'to_user_id' => $user->id, 'body' => 'Sveiki']);
        $this->addNotification($user, NewMessage::class, ['message' => 'x', 'sender_id' => $anna->id]);
        $this->addNotification($user, NewMessage::class, ['message' => 'x', 'sender_id' => $peteris->id]);

        $this->actingAs($user)->getJson('/api/messages/' . $anna->id)->assertOk();

        // Pētera paziņojums paliek
        $this->assertSame(1, $user->unreadNotifications()->count());
        $this->assertSame($peteris->id, $user->unreadNotifications()->first()->data['sender_id']);
    }

    public function test_cannot_read_someone_elses_notification(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->addNotification($other, SwapUnavailable::class, ['message' => 'x']);
        $id = $other->notifications()->first()->id;

        $this->actingAs($user)->patchJson("/api/notifications/$id/read")->assertNotFound();
        $this->assertNull($other->notifications()->first()->read_at);
    }

    public function test_mark_all_read_and_clear_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->addNotification($user, SwapUnavailable::class, ["message" => "x"]);
        }
        $this->addNotification($other, SwapUnavailable::class, ["message" => "x"]);

        $this->actingAs($user)->postJson("/api/notifications/read-all")->assertOk();
        $this->assertSame(0, $user->unreadNotifications()->count());

        // jauns pēc tam paliek nelasīts un "notīrīt" to neizdzēš
        $this->addNotification($user, SwapUnavailable::class, ["message" => "jauns"]);
        $this->actingAs($user)->deleteJson("/api/notifications/read")->assertNoContent();

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame("jauns", $user->notifications()->first()->data["message"]);
        // cita lietotāja paziņojumi neaiztikti
        $this->assertSame(1, $other->unreadNotifications()->count());
    }
}
