<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// šeit īstais disks, jo Storage::fake neveido īstas parakstītas saites
class MessageImageLinkTest extends TestCase
{
    use RefreshDatabase;

    private string $png;
    private ?string $path = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post('/api/messages', [
            'to_user_id' => $b->id,
            'image' => UploadedFile::fake()->createWithContent('photo.png', $this->png),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->path = Message::first()->image_path;
    }

    protected function tearDown(): void
    {
        if ($this->path) {
            Storage::disk('local')->delete($this->path);
        }

        parent::tearDown();
    }

    public function test_image_needs_signed_link(): void
    {
        $this->get('/storage/' . $this->path)->assertForbidden();

        $response = $this->get(Message::first()->image_url)->assertOk();
        $this->assertSame($this->png, $response->streamedContent());
    }

    public function test_image_link_expires(): void
    {
        $url = Message::first()->image_url;

        $this->travel(3)->hours();

        $this->get($url)->assertForbidden();
    }
}
