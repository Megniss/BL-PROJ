<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ServerBoot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// servera palaišana izlogo visus
class ServerBootTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_deletes_all_tokens_and_changes_id(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;
        $this->withToken($token)->getJson('/api/me')->assertOk();

        $before = ServerBoot::id();
        ServerBoot::start();

        $this->assertNotSame($before, ServerBoot::id());
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_all_command(): void
    {
        User::factory()->create()->createToken('auth_token');

        $this->artisan('auth:logout-all')->assertSuccessful();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // lapa ieliek id <meta>, lai pārlūks var salīdzināt
    public function test_page_has_server_boot_meta(): void
    {
        ServerBoot::start();

        $this->get('/')->assertSee('<meta name="server-boot" content="' . ServerBoot::id() . '">', false);
    }
}
