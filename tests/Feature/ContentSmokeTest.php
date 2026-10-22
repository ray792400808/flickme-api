<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\AdminUser;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 冒烟：用真实 HTTP 链路走一遍内容管理四大模块的关键路径。
 */
class ContentSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    private function headers(): array
    {
        $admin = AdminUser::factory()->create();
        $role = AdminRole::query()->where('slug', 'super_admin')->first();
        $admin->roles()->attach($role->id);
        $token = bin2hex(random_bytes(32));
        Cache::put('admin:token:'.hash('sha256', $token), [
            'id' => $admin->id,
            'version' => $admin->tokenVersion(),
        ], now()->addHour());

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_full_content_lifecycle(): void
    {
        $h = $this->headers();

        // 1. 语言列表
        $lang = $this->postJson('/admin/languages', [
            'code' => 'zh-Hans-CN', 'name' => 'Chinese (Simplified)', 'native_name' => '简体中文', 'sort' => 10,
        ], $h)->assertOk()->json('data.id');

        // 2. 角标
        $badge = $this->postJson('/admin/badges', ['keyword' => 'HOT', 'label' => '热门'], $h)
            ->assertOk()->json('data.id');

        // 3. 标签与合集（带多语翻译）
        $tag = $this->postJson('/admin/tags', [
            'code' => 'romance', 'type' => 1, 'name' => 'Romance', 'description' => 'Love story',
            'language_id' => $lang, 'language_code' => 'zh-Hans-CN',
            'translations' => [['language_id' => $lang, 'name' => '爱情', 'description' => '爱情故事']],
        ], $h)->assertOk()->json('data.id');

        // 4. 剧库 + 分集
        $drama = $this->postJson('/admin/dramas', [
            'title' => '测试剧', 'language_id' => $lang, 'badge_id' => $badge,
            'free_episodes' => 6, 'tag_ids' => [$tag],
            'episodes' => [
                ['episode_no' => 1, 'title' => '第1集', 'duration_sec' => 120],
                ['episode_no' => 2, 'title' => '第2集', 'duration_sec' => 130],
            ],
        ], $h)->assertOk();
        $dramaId = $drama->json('data.id');

        $this->assertSame(2, $drama->json('data.episodes_num'));
        $this->assertSame(6, $drama->json('data.free_episodes'));
        $this->assertSame('HOT', $drama->json('data.badge.keyword'));

        // 5. 修改分集 → episodes_num 重算
        $episodeId = $this->getJson('/admin/dramas/'.$dramaId.'/episodes', $h)->json('data.0.id');
        $this->patchJson('/admin/dramas/'.$dramaId.'/episodes/'.$episodeId, [
            'episode_no' => 5, 'title' => '第5集',
        ], $h)->assertOk()->assertJsonPath('data.episode_no', 5);
        $this->assertDatabaseHas('dramas', ['id' => $dramaId, 'episodes_num' => 2]);

        // 6. 删除分集 → episodes_num 回退
        $this->deleteJson('/admin/dramas/'.$dramaId.'/episodes/'.$episodeId, [], $h)->assertOk();
        $this->assertDatabaseHas('dramas', ['id' => $dramaId, 'episodes_num' => 1]);

        // 7. 标签翻译在对应语种下生效
        $this->getJson('/admin/tags?lang=zh-Hans-CN', $h)
            ->assertOk()
            ->assertJsonPath('data.data.0.name', '爱情')
            ->assertJsonPath('data.data.0.description', '爱情故事');

        // 8. 剧库筛选（角标 / 标签）
        $this->getJson('/admin/dramas?badge_id='.$badge, $h)->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/admin/dramas?tag_id='.$tag, $h)->assertOk()->assertJsonPath('data.total', 1);

        // 9. 软删除
        $this->deleteJson('/admin/dramas/'.$dramaId, [], $h)->assertOk();
        $this->assertSoftDeleted('dramas', ['id' => $dramaId]);
    }
}
