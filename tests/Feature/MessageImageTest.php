<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageImageTest extends TestCase
{
    use RefreshDatabase;

    // īsta 1x1 PNG bilde, jo serverī nav GD fake bildēm
    private function image(string $name = 'photo.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    private function sendImage(User $from, User $to, string $body = '')
    {
        return $this->actingAs($from)->post('/api/messages', [
            'to_user_id' => $to->id,
            'body' => $body,
            'image' => $this->image(),
        ], ['Accept' => 'application/json']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_can_send_image_without_text(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $response = $this->sendImage($a, $b)->assertCreated();

        $message = Message::first();
        $this->assertNotNull($message->image_path);
        Storage::disk('local')->assertExists($message->image_path);
        $this->assertNotNull($response->json('image_url'));
        $this->assertArrayNotHasKey('image_path', $response->json());
    }

    public function test_message_needs_text_or_image(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->postJson('/api/messages', ['to_user_id' => $b->id, 'body' => ''])
             ->assertStatus(422);
    }

    public function test_rejects_non_image_file(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post('/api/messages', [
            'to_user_id' => $b->id,
            'image' => UploadedFile::fake()->createWithContent('notes.txt', 'hello'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_rejects_too_large_image(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $big = $this->image()->size(6000);

        $this->actingAs($a)->post('/api/messages', [
            'to_user_id' => $b->id,
            'image' => $big,
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_conversation_preview_marks_image(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->sendImage($a, $b);

        $this->actingAs($b)->getJson('/api/messages')
             ->assertOk()
             ->assertJsonPath('0.last_message.image', true);
    }

    public function test_unsend_deletes_image(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->sendImage($a, $b);

        $message = Message::first();
        $path = $message->image_path;

        $this->actingAs($a)->deleteJson("/api/messages/{$message->id}")->assertNoContent();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_image_message_text_can_be_emptied(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->sendImage($a, $b, 'Skaties');

        $message = Message::first();

        $this->actingAs($a)->putJson("/api/messages/{$message->id}", ['body' => ''])
             ->assertOk()
             ->assertJsonPath('body', '');
    }

    public function test_account_deletion_removes_sent_images(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->sendImage($a, $b, 'Mana bilde');

        $message = Message::first();
        $path = $message->image_path;

        $this->actingAs($a)->deleteJson('/api/profile', ['password' => 'password'])->assertNoContent();

        $message->refresh();
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($message->image_path);
        $this->assertTrue($message->image_removed);
        $this->assertSame('Mana bilde', $message->body);
    }
}
