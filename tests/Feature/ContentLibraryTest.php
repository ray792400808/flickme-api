<?php

namespace Tests\Feature;

use App\Models\AdminAccount;
use App\Models\Drama;
use App\Models\StoryCharacter;
use App\Models\Tag;
use Database\Seeders\DevelopmentContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleted_content_is_hidden_and_seeding_does_not_restore_it(): void
    {
        $this->seed(DevelopmentContentSeeder::class);
        $headers = $this->headers();
        Tag::where('key', 'romance')->firstOrFail()->delete();
        Drama::findOrFail(1)->delete();
        StoryCharacter::findOrFail(1)->delete();
        $this->seed(DevelopmentContentSeeder::class);
        $this->assertSoftDeleted('tags', ['key' => 'romance']);
        $this->assertSoftDeleted('dramas', ['id' => 1]);
        $this->assertSoftDeleted('story_characters', ['id' => 1]);
        $this->getJson('/admin/v1/dramas/1', $headers)->assertNotFound();
        $this->getJson('/admin/v1/characters/1', $headers)->assertNotFound();
        $this->getJson('/admin/v1/tags?status=active', $headers)->assertOk()->assertJsonPath('data.total', 4);
        $draft = $this->getJson('/admin/v1/ops/launch', $headers)->json('data.draft');
        $draft['enabled'] = true;
        $draft['fallback_drama_id'] = 1;
        $this->putJson('/admin/v1/ops/launch', ['revision' => 1, 'draft' => $draft], $headers)->assertUnprocessable();
        $chips = $this->getJson('/admin/v1/ops/chips?channel=short', $headers)->json('data.draft');
        $this->putJson('/admin/v1/ops/chips?channel=short', ['revision' => 1, 'draft' => $chips], $headers)->assertUnprocessable();
    }

    private function headers(string $role = 'admin'): array
    {
        $admin = AdminAccount::factory()->create(['role_key' => $role]);
        $token = bin2hex(random_bytes(32));
        Cache::put('admin:tokens:'.hash('sha256', $token), ['id' => $admin->id, 'version' => $admin->token_version], now()->addHour());

        return ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => 'content-publish'];
    }

    public function test_seed_is_repeatable_and_preserves_existing_content_links_and_configs(): void
    {
        $this->seed(DevelopmentContentSeeder::class);
        $drama = Drama::findOrFail(1);
        $drama->update(['title' => 'Edited title']);
        $drama->tags()->detach();
        DB::table('operation_configs')->where('channel', 'short')->update(['revision' => 7]);
        $this->seed(DevelopmentContentSeeder::class);
        $this->assertDatabaseCount('tags', 5);
        $this->assertDatabaseCount('dramas', 10);
        $this->assertDatabaseCount('story_characters', 5);
        $this->assertSame('Edited title', $drama->fresh()->title);
        $this->assertCount(0, $drama->fresh()->tags);
        $this->assertDatabaseHas('operation_configs', ['channel' => 'short', 'revision' => 7]);
        $stories = json_decode(DB::table('operation_configs')->where('channel', 'stories')->value('draft'), true);
        $this->assertSame('for_you', $stories[0]['key']);
        $this->assertTrue($stories[0]['locked']);
        $this->assertCount(6, $stories);
    }

    public function test_seed_is_blocked_in_production_before_any_insert(): void
    {
        app()->instance('env', 'production');
        try {
            (new DevelopmentContentSeeder)->run();
            $this->fail('Must reject production');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('仅允许', $exception->getMessage());
        } finally {
            app()->instance('env', 'testing');
        }
        $this->assertDatabaseCount('tags', 0);
    }

    public function test_queries_support_ids_status_pagination_and_tag_filters(): void
    {
        $this->seed(DevelopmentContentSeeder::class);
        $headers = $this->headers('ops');
        $this->getJson('/admin/v1/tags?status=active', $headers)->assertOk()->assertJsonPath('data.total', 5);
        $this->getJson('/admin/v1/dramas?tag_key=romance&available=1&per_page=100', $headers)->assertOk()->assertJsonPath('data.total', 3)->assertJsonPath('data.data.0.id', 1);
        $this->getJson('/admin/v1/dramas?status=draft', $headers)->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson('/admin/v1/characters?tag_key=fantasy', $headers)->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', 3);
        $this->getJson('/admin/v1/dramas/1', $headers)->assertOk()->assertJsonPath('data.video_url', '/test-media/sample.mp4');
        $this->getJson('/admin/v1/characters/1', $headers)->assertOk();
        $this->getJson('/admin/v1/tags/2', $headers)->assertOk()->assertJsonPath('data.key', 'ceo');
        $this->getJson('/admin/v1/tags')->assertUnauthorized();
        $this->getJson('/admin/v1/tags', $this->headers('finance'))->assertForbidden();
        $this->getJson('/admin/v1/dramas?per_page=101', $headers)->assertUnprocessable();
    }

    public function test_launch_and_recommendations_reject_unavailable_or_missing_drama(): void
    {
        $this->seed(DevelopmentContentSeeder::class);
        $headers = $this->headers();
        $draft = $this->getJson('/admin/v1/ops/launch', $headers)->assertOk()->json('data.draft');
        foreach ([999, 7, 9] as $id) {
            $draft['fallback_drama_id'] = $id;
            $draft['enabled'] = true;
            $this->putJson('/admin/v1/ops/launch', ['revision' => 1, 'draft' => $draft], $headers)->assertUnprocessable();
        }
        $draft['fallback_drama_id'] = 1;
        $this->putJson('/admin/v1/ops/launch', ['revision' => 1, 'draft' => $draft], $headers)->assertOk();
        Drama::findOrFail(1)->update(['is_playable' => false]);
        $this->postJson('/admin/v1/ops/launch/publish', ['revision' => 2], $headers)->assertUnprocessable();
        $recs = $this->getJson('/admin/v1/ops/recs', $headers)->assertOk()->json('data.draft');
        $recs[0]['pinned_drama_id'] = 7;
        $this->putJson('/admin/v1/ops/recs', ['revision' => 1, 'draft' => $recs], $headers)->assertUnprocessable();
        $recs[0]['pinned_drama_id'] = 2;
        $this->putJson('/admin/v1/ops/recs', ['revision' => 1, 'draft' => $recs], $headers)->assertOk();
    }

    public function test_categories_reject_disabled_and_missing_tags(): void
    {
        $this->seed(DevelopmentContentSeeder::class);
        $headers = $this->headers();
        $draft = $this->getJson('/admin/v1/ops/chips?channel=short', $headers)->json('data.draft');
        $this->putJson('/admin/v1/ops/chips?channel=short', ['revision' => 1, 'draft' => $draft], $headers)->assertOk();
        Tag::where('key', 'romance')->update(['status' => 'disabled']);
        $this->putJson('/admin/v1/ops/chips?channel=short', ['revision' => 2, 'draft' => $draft], $headers)->assertUnprocessable();
        $draft[1]['key'] = 'missing';
        $this->putJson('/admin/v1/ops/chips?channel=short', ['revision' => 2, 'draft' => $draft], $headers)->assertUnprocessable();
    }

    public function test_hot_pool_uses_real_metadata_and_persists_override_flags(): void
    {
        $this->seed(DevelopmentContentSeeder::class);
        $headers = $this->headers();
        $draft = $this->getJson('/admin/v1/ops/hot', $headers)->assertOk()->json('data.draft');
        $this->assertCount(6, $draft['overrides']);
        $draft['overrides'][0]['pinned'] = true;
        $this->putJson('/admin/v1/ops/hot', ['revision' => 1, 'draft' => $draft], $headers)->assertOk()->assertJsonPath('data.draft.overrides.0.pinned', true);
        $draft['overrides'][0]['heat'] = 9999;
        $this->putJson('/admin/v1/ops/hot', ['revision' => 2, 'draft' => $draft], $headers)->assertUnprocessable();
        $this->assertFileExists(public_path('test-media/sample.mp4'));
        $this->assertFileExists(public_path('test-media/cover.svg'));
    }
}
