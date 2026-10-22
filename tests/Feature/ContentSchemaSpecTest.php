<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 校验内容管理四张表（languages / dramas / drama_episodes / badges / tags /
 * tag_translations / drama_tag）的最终结构与用户确认的 SQL 一致。
 */
class ContentSchemaSpecTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_tables_match_final_spec(): void
    {
        foreach (['languages', 'dramas', 'drama_episodes', 'badges', 'tags', 'tag_translations', 'drama_tag'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "缺少表 {$table}");
        }

        // languages
        $this->assertFalse(Schema::hasColumn('languages', 'is_default'));
        foreach (['code', 'name', 'native_name', 'flag_emoji', 'enabled', 'sort', 'created_at', 'updated_at', 'deleted_at'] as $col) {
            $this->assertTrue(Schema::hasColumn('languages', $col), "languages 缺少 {$col}");
        }

        // dramas
        foreach ([
            'title', 'synopsis', 'cover_url', 'video_url', 'status', 'badge_id', 'language_id',
            'free_episodes', 'episodes_num', 'creator_admin_id', 'updated_by', 'published_at',
            'created_at', 'updated_at', 'deleted_at',
        ] as $col) {
            $this->assertTrue(Schema::hasColumn('dramas', $col), "dramas 缺少 {$col}");
        }

        // drama_episodes
        foreach ([
            'drama_id', 'episode_no', 'title', 'duration_sec', 'status', 'is_pay',
            'video_url', 'poster_url', 'created_at', 'updated_at', 'deleted_at',
        ] as $col) {
            $this->assertTrue(Schema::hasColumn('drama_episodes', $col), "drama_episodes 缺少 {$col}");
        }

        // badges
        foreach (['keyword', 'label', 'sort', 'enabled', 'created_at', 'updated_at', 'deleted_at'] as $col) {
            $this->assertTrue(Schema::hasColumn('badges', $col), "badges 缺少 {$col}");
        }

        // tags
        foreach ([
            'code', 'type', 'name', 'description', 'status', 'language_id', 'language_code',
            'creator_admin_id', 'created_at', 'updated_at', 'deleted_at',
        ] as $col) {
            $this->assertTrue(Schema::hasColumn('tags', $col), "tags 缺少 {$col}");
        }

        // tag_translations
        foreach ([
            'tag_id', 'language_id', 'name', 'description', 'language_code',
            'created_at', 'updated_at', 'deleted_at',
        ] as $col) {
            $this->assertTrue(Schema::hasColumn('tag_translations', $col), "tag_translations 缺少 {$col}");
        }

        // drama_tag
        foreach (['drama_id', 'tag_id', 'created_at'] as $col) {
            $this->assertTrue(Schema::hasColumn('drama_tag', $col), "drama_tag 缺少 {$col}");
        }

        // 旧字段不应残留
        $this->assertFalse(Schema::hasColumn('tags', 'key'));
        $this->assertFalse(Schema::hasColumn('dramas', 'heat'));
        $this->assertFalse(Schema::hasColumn('dramas', 'is_playable'));
    }

    public function test_dramas_defaults_match_spec(): void
    {
        // free_episodes 默认 6、episodes_num 默认 0：只填必填项建剧后读回
        $languageId = DB::table('languages')->insertGetId([
            'code' => 'en-US',
            'name' => 'English',
            'native_name' => 'English',
            'enabled' => 1,
            'sort' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $dramaId = DB::table('dramas')->insertGetId([
            'title' => 'Default Drama',
            'status' => 1,
            'language_id' => $languageId,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $row = DB::table('dramas')->where('id', $dramaId)->first();

        $this->assertSame(6, (int) $row->free_episodes, 'free_episodes 默认值应为 6');
        $this->assertSame(0, (int) $row->episodes_num, 'episodes_num 默认值应为 0');
        $this->assertSame(1, (int) $row->status, 'status 默认值应为 1（上架）');
    }
}
