<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\AdminUser;
use Database\Seeders\AdminSeeder;
use Database\Seeders\ContentMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 内容管理四大模块（剧库 / 标签与合集 / 角标 / 语言列表）的接口契约测试。
 */
class ContentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 权限与超级管理员角色（CheckAdminPermission 依赖 content.* 权限码）
        $this->seed(AdminSeeder::class);
        $this->seed(ContentMenuSeeder::class);
    }

    private function headers(string $role = 'super_admin'): array
    {
        $admin = AdminUser::factory()->create();
        $roleModel = AdminRole::query()->where('slug', $role)->first();

        if ($roleModel) {
            $admin->roles()->attach($roleModel->id);
        }

        $token = bin2hex(random_bytes(32));
        Cache::put('admin:token:'.hash('sha256', $token), [
            'id' => $admin->id,
            'version' => $admin->tokenVersion(),
        ], now()->addHour());

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_languages_crud(): void
    {
        $headers = $this->headers();

        $this->getJson('/admin/languages', $headers)
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        $created = $this->postJson('/admin/languages', [
            'code' => 'en-US',
            'name' => 'English (US)',
            'native_name' => 'English',
            'sort' => 10,
        ], $headers)->assertOk()->assertJsonPath('data.code', 'en-US');

        $id = $created->json('data.id');

        // 重复 code 应被拒绝
        $this->postJson('/admin/languages', [
            'code' => 'en-US',
            'name' => 'Dup',
            'native_name' => 'Dup',
        ], $headers)->assertUnprocessable();

        // 非 BCP47 代码应被拒绝
        $this->postJson('/admin/languages', [
            'code' => '中文',
            'name' => 'Bad',
            'native_name' => 'Bad',
        ], $headers)->assertUnprocessable();

        $this->getJson('/admin/languages/options', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.code', 'en-US');

        $this->patchJson('/admin/languages/'.$id, ['native_name' => '英语'], $headers)
            ->assertOk()
            ->assertJsonPath('data.native_name', '英语');

        $this->deleteJson('/admin/languages/'.$id, [], $headers)->assertOk();
        $this->assertSoftDeleted('languages', ['id' => $id]);
    }

    public function test_language_cannot_be_deleted_when_referenced(): void
    {
        $headers = $this->headers();

        $languageId = $this->postJson('/admin/languages', [
            'code' => 'en-US', 'name' => 'English', 'native_name' => 'English',
        ], $headers)->assertOk()->json('data.id');

        $this->postJson('/admin/tags', [
            'code' => 'romance', 'type' => 1, 'name' => 'Romance',
            'language_id' => $languageId, 'language_code' => 'en-US',
        ], $headers)->assertOk();

        $this->deleteJson('/admin/languages/'.$languageId, [], $headers)->assertStatus(422);
    }

    public function test_badges_crud(): void
    {
        $headers = $this->headers();

        $created = $this->postJson('/admin/badges', [
            'keyword' => 'HOT',
            'label' => '热门',
            'sort' => 10,
        ], $headers)->assertOk()->assertJsonPath('data.keyword', 'HOT');

        $id = $created->json('data.id');

        $this->getJson('/admin/badges', $headers)->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/admin/badges/options', $headers)->assertOk()->assertJsonPath('data.0.keyword', 'HOT');

        $this->patchJson('/admin/badges/'.$id, ['label' => '热门中'], $headers)
            ->assertOk()
            ->assertJsonPath('data.label', '热门中');

        $this->deleteJson('/admin/badges/'.$id, [], $headers)->assertOk();
        $this->assertSoftDeleted('badges', ['id' => $id]);
    }

    public function test_tags_crud_with_translations_and_pivot(): void
    {
        $headers = $this->headers();

        $en = $this->postJson('/admin/languages', [
            'code' => 'en-US', 'name' => 'English', 'native_name' => 'English',
        ], $headers)->assertOk()->json('data.id');

        $zh = $this->postJson('/admin/languages', [
            'code' => 'zh-Hans-CN', 'name' => 'Chinese', 'native_name' => '简体中文',
        ], $headers)->assertOk()->json('data.id');

        $created = $this->postJson('/admin/tags', [
            'code' => 'romance',
            'type' => 1,
            'name' => 'Romance',
            'description' => 'Love story',
            'language_id' => $en,
            'language_code' => 'en-US',
            'translations' => [
                ['language_id' => $zh, 'name' => '爱情', 'description' => '爱情故事'],
            ],
        ], $headers)->assertOk()->assertJsonPath('data.code', 'romance');

        $id = $created->json('data.id');

        $this->assertDatabaseHas('tag_translations', [
            'tag_id' => $id,
            'language_id' => $zh,
            'name' => '爱情',
        ]);

        // 按中文语种查询时返回翻译后的名称
        $this->getJson('/admin/tags?lang=zh-Hans-CN', $headers)
            ->assertOk()
            ->assertJsonPath('data.data.0.name', '爱情')
            ->assertJsonPath('data.data.0.description', '爱情故事');

        $this->getJson('/admin/tags/stats', $headers)->assertOk();

        $this->patchJson('/admin/tags/'.$id, [
            'status' => 2,
        ], $headers)->assertOk()->assertJsonPath('data.status', 2);

        $this->deleteJson('/admin/tags/'.$id, [], $headers)->assertOk();
        $this->assertSoftDeleted('tags', ['id' => $id]);
    }

    public function test_dramas_crud_with_episodes_and_pivot(): void
    {
        $headers = $this->headers();

        $lang = $this->postJson('/admin/languages', [
            'code' => 'en-US', 'name' => 'English', 'native_name' => 'English',
        ], $headers)->assertOk()->json('data.id');

        $badge = $this->postJson('/admin/badges', ['keyword' => 'HOT'], $headers)
            ->assertOk()->json('data.id');

        $tag = $this->postJson('/admin/tags', [
            'code' => 'romance', 'type' => 1, 'name' => 'Romance',
            'language_id' => $lang, 'language_code' => 'en-US',
        ], $headers)->assertOk()->json('data.id');

        $created = $this->postJson('/admin/dramas', [
            'title' => 'The CEO\'s Trap',
            'language_id' => $lang,
            'badge_id' => $badge,
            'free_episodes' => 6,
            'tag_ids' => [$tag],
            'episodes' => [
                ['episode_no' => 1, 'title' => 'Pilot', 'duration_sec' => 180, 'is_pay' => 0],
                ['episode_no' => 2, 'title' => 'Reveal', 'duration_sec' => 200, 'is_pay' => 1],
            ],
        ], $headers)->assertOk()->assertJsonPath('data.episodes_num', 2);

        $id = $created->json('data.id');

        $this->assertDatabaseCount('drama_episodes', 2);
        $this->assertDatabaseHas('drama_tag', ['drama_id' => $id, 'tag_id' => $tag]);

        // 列表按角标 / 标签过滤
        $this->getJson('/admin/dramas?badge_id='.$badge, $headers)
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->getJson('/admin/dramas?tag_id='.$tag, $headers)
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->getJson('/admin/dramas/'.$id, $headers)
            ->assertOk()
            ->assertJsonPath('data.title', "The CEO's Trap")
            ->assertJsonPath('data.episodes.0.episode_no', 1);

        // 新增分集后 episodes_num 同步
        $this->postJson('/admin/dramas/'.$id.'/episodes', [
            'episode_no' => 3, 'duration_sec' => 150,
        ], $headers)->assertOk();

        $this->assertDatabaseHas('dramas', ['id' => $id, 'episodes_num' => 3]);

        // 集号冲突
        $this->postJson('/admin/dramas/'.$id.'/episodes', [
            'episode_no' => 3,
        ], $headers)->assertStatus(422);

        // 删除分集后 episodes_num 回退
        $episodeId = $this->getJson('/admin/dramas/'.$id.'/episodes', $headers)
            ->json('data.0.id');

        $this->deleteJson('/admin/dramas/'.$id.'/episodes/'.$episodeId, [], $headers)->assertOk();
        $this->assertDatabaseHas('dramas', ['id' => $id, 'episodes_num' => 2]);

        // 上架时写入 published_at
        $this->patchJson('/admin/dramas/'.$id, ['status' => 1], $headers)->assertOk();
        $this->assertNotNull(
            $this->getJson('/admin/dramas/'.$id, $headers)->json('data.published_at')
        );

        $this->deleteJson('/admin/dramas/'.$id, [], $headers)->assertOk();
        $this->assertSoftDeleted('dramas', ['id' => $id]);
    }

    public function test_episode_update_keeps_episodes_num_in_sync(): void
    {
        $headers = $this->headers();

        $lang = $this->postJson('/admin/languages', [
            'code' => 'en-US', 'name' => 'English', 'native_name' => 'English',
        ], $headers)->assertOk()->json('data.id');

        $dramaId = $this->postJson('/admin/dramas', [
            'title' => 'Sync Drama',
            'language_id' => $lang,
            'episodes' => [
                ['episode_no' => 1, 'title' => 'EP1'],
                ['episode_no' => 2, 'title' => 'EP2'],
            ],
        ], $headers)->assertOk()->json('data.id');

        $episodeId = $this->getJson('/admin/dramas/'.$dramaId.'/episodes', $headers)
            ->json('data.0.id');

        // 修改分集（集号 + 状态）后 episodes_num 必须重新计算
        $this->patchJson('/admin/dramas/'.$dramaId.'/episodes/'.$episodeId, [
            'episode_no' => 5,
            'status' => 2,
            'title' => 'EP1 修订',
        ], $headers)->assertOk()->assertJsonPath('data.episode_no', 5);

        $this->assertDatabaseHas('dramas', ['id' => $dramaId, 'episodes_num' => 2]);

        // 非法集号应被拒绝
        $this->patchJson('/admin/dramas/'.$dramaId.'/episodes/'.$episodeId, [
            'episode_no' => 0,
        ], $headers)->assertUnprocessable();
    }

    public function test_content_endpoints_require_authentication(): void
    {
        $this->getJson('/admin/languages')->assertUnauthorized();
        $this->getJson('/admin/badges')->assertUnauthorized();
        $this->getJson('/admin/tags')->assertUnauthorized();
        $this->getJson('/admin/dramas')->assertUnauthorized();
    }
}
