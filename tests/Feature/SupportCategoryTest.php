<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// atbalsta pieteikuma iemesls no saraksta, savs temats tikai pie "other"
class SupportCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_sets_subject_automatically(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/api/complaints', ['category' => 'not_received', 'body' => 'Grāmata neatnāca'])
            ->assertCreated()
            ->assertJsonPath('category', 'not_received')
            ->assertJsonPath('subject', 'Did not receive a book');

        // sarakstā arī ir iemesls
        $this->actingAs($user)->getJson('/api/complaints')->assertJsonPath('0.category', 'not_received');
    }

    public function test_subject_sent_with_category_is_ignored(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/complaints', ['category' => 'bug', 'subject' => 'kaut kas cits', 'body' => 'x'])
            ->assertCreated()
            ->assertJsonPath('subject', 'Website error');
    }

    public function test_other_needs_own_subject(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/complaints', ['category' => 'other', 'body' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('subject');

        $this->actingAs($user)->postJson('/api/complaints', ['category' => 'other', 'subject' => 'Mans temats', 'body' => 'x'])
            ->assertCreated()
            ->assertJsonPath('subject', 'Mans temats');
    }

    public function test_unknown_category_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/complaints', ['category' => 'hack', 'body' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('category');
        $this->assertDatabaseCount('complaints', 0);
    }

    // vecais veids (tikai temats) joprojām strādā, iemesls = other
    public function test_without_category_works_like_before(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/complaints', ['subject' => 'Problēma', 'body' => 'x'])
            ->assertCreated()
            ->assertJsonPath('category', 'other');
    }

    public function test_server_and_frontend_have_same_categories(): void
    {
        $js = file_get_contents(resource_path('js/components/Support.vue'));
        preg_match_all("/\{ key: '([a-z_]+)', icon:/", $js, $m);

        $this->assertSame(array_keys(Complaint::CATEGORIES), $m[1]);
    }
}
