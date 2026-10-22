<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 剧库 / 标签与合集 / 角标 / 语言 四大内容模块的表结构。
 *
 * 背景：早期版本已有 tags / dramas / drama_tag 三张表，字段是演示用的
 * 简化结构（tags.key + tags.status 为字符串，dramas.status 为字符串，
 * dramas 带 is_playable / heat）。本次按新的内容中台结构重建：
 *
 *   - tags.key        → tags.code       （稳定标识）
 *   - tags.status     → tags.status     （改为 tinyint：1 启用 / 2 停用）
 *   - 新增 tags.type      （1 标签 / 2 合集）
 *   - 新增 tags.language_id / language_code（默认语种 + 多语翻译）
 *   - 新增 tag_translations（多语言名称与描述）
 *   - dramas.status   → 改为 tinyint（0 草稿 / 1 上架 / 2 下架）
 *   - 新增 dramas.badge_id / language_id / free_episodes / episodes_num
 *   - 新增 drama_episodes（分集）
 *   - 新增 badges（角标字典）
 *   - 新增 languages（语言字典）
 *
 * 旧表数据均为演示/开发数据（当前库中行数为 0），因此直接重建，
 * 不做数据搬迁；story_characters / story_character_tag 不在本模块范围内，
 * 保留原样并解除其对 tags 的外键依赖。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 清理旧的外键与中间表 ──────────────────────────
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('story_character_tag');
        Schema::dropIfExists('drama_tag');
        Schema::dropIfExists('drama_episodes');
        Schema::dropIfExists('dramas');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('tag_translations');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('languages');

        Schema::enableForeignKeyConstraints();

        // ─────────────────────────────────────────
        // 1. languages
        // ─────────────────────────────────────────
        Schema::create('languages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 16)->unique()->comment('BCP47：zh-CN / en-US / ja-JP / es-ES / ar-SA');
            $table->string('name', 100)->comment('英文名：Chinese (Simplified)');
            $table->string('native_name', 100)->comment('本地名：简体中文');
            $table->string('flag_emoji', 16)->nullable()->comment('🇨🇳');
            $table->tinyInteger('is_default')->unsigned()->default(0)->comment('是否系统默认语种；全局至多 1 行');
            $table->tinyInteger('enabled')->unsigned()->default(1)->comment('是否启用；禁用后翻译不再被前端采用');
            $table->unsignedInteger('sort')->default(0)->comment('语言切换器展示顺序');
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();
            $table->unsignedBigInteger('deleted_at')->nullable();

            $table->index('enabled');
            $table->index('is_default');
            $table->index('sort');
        });

        // ─────────────────────────────────────────
        // 2. badges（角标字典）
        // ─────────────────────────────────────────
        Schema::create('badges', function (Blueprint $table): void {
            $table->id();
            $table->string('keyword', 64)->unique()->comment('稳定关键词，如 HOT / NEW / EXCLUSIVE');
            $table->string('label', 50)->nullable()->comment('显示名称（单语）');
            $table->unsignedInteger('sort')->default(0)->comment('多角标并存时的展示顺序');
            $table->tinyInteger('enabled')->unsigned()->default(1)->comment('是否启用');
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();
            $table->unsignedBigInteger('deleted_at')->nullable();

            $table->index('enabled');
        });

        // ─────────────────────────────────────────
        // 3. tags（标签与合集）
        // ─────────────────────────────────────────
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique()->comment('稳定标识，如 romance / thriller');
            $table->tinyInteger('type')->unsigned()->default(1)->comment('1=标签 / 2=合集');
            $table->string('name', 200)->comment('默认语言名称');
            $table->text('description')->nullable()->comment('默认语描述');
            $table->tinyInteger('status')->unsigned()->default(1)->comment('1=启用 / 2=停用');
            $table->foreignId('language_id')->comment('name 字段对应的默认语种 ID');
            $table->string('language_code', 16)->default('en-US')->comment('与 language_id 一致，缓存用');
            $table->foreignId('creator_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();
            $table->unsignedBigInteger('deleted_at')->nullable();

            $table->index(['type', 'status']);
            $table->index('language_id');
        });

        // ─────────────────────────────────────────
        // 4. tag_translations
        // ─────────────────────────────────────────
        Schema::create('tag_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete();
            $table->string('name', 200)->comment('该语种名称');
            $table->text('description')->nullable()->comment('该语种描述');
            $table->string('language_code', 16)->comment('BCP47 code 缓存');
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();
            $table->unsignedBigInteger('deleted_at')->nullable();

            $table->unique(['tag_id', 'language_id']);
            $table->index('tag_id');
            $table->index('language_id');
            $table->index('language_code');
        });

        // ─────────────────────────────────────────
        // 5. dramas（剧库）
        // ─────────────────────────────────────────
        Schema::create('dramas', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200)->comment('默认语剧名');
            $table->text('synopsis')->nullable()->comment('剧级简介');
            $table->string('cover_url', 1024)->nullable()->comment('剧封图 URL');
            $table->string('video_url', 1024)->nullable()->comment('花絮/预告 URL');
            $table->tinyInteger('status')->unsigned()->default(1)->comment('0=草稿 / 1=上架 / 2=下架');
            $table->foreignId('badge_id')->nullable()->constrained('badges')->nullOnDelete()->comment('角标（单选）');
            $table->foreignId('language_id')->comment('原始 / 默认语种 ID');
            $table->unsignedInteger('free_episodes')->default(6)->comment('默认免费集数');
            $table->unsignedInteger('episodes_num')->default(0)->comment('总集数（冗余字段，由分集数维护）');
            $table->foreignId('creator_admin_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('admin_users')->nullOnDelete()->comment('最近更新者');
            $table->unsignedBigInteger('published_at')->nullable()->comment('上架时间');
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();
            $table->unsignedBigInteger('deleted_at')->nullable();

            $table->index('status');
            $table->index(['status', 'language_id']);
            $table->index('badge_id');
            $table->index('published_at');
            $table->index('creator_admin_id');
            $table->index('deleted_at');
        });

        // ─────────────────────────────────────────
        // 6. drama_episodes（分集）
        // ─────────────────────────────────────────
        Schema::create('drama_episodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('drama_id')->constrained('dramas')->cascadeOnDelete();
            $table->unsignedInteger('episode_no')->comment('集号 1..N；单剧下唯一');
            $table->string('title', 200)->nullable()->comment('该集标题');
            $table->unsignedInteger('duration_sec')->default(0)->comment('集长（秒）');
            $table->tinyInteger('status')->unsigned()->default(1)->comment('0=草稿 / 1=上架 / 2=下架');
            $table->tinyInteger('is_pay')->unsigned()->default(0)->comment('是否付费：0=否 / 1=是');
            $table->string('video_url', 1024)->nullable()->comment('主播放源（m3u8/mp4）');
            $table->string('poster_url', 1024)->nullable()->comment('集封面');
            $table->unsignedBigInteger('created_at')->nullable();
            $table->unsignedBigInteger('updated_at')->nullable();
            $table->unsignedBigInteger('deleted_at')->nullable();

            $table->unique(['drama_id', 'episode_no']);
            $table->index(['drama_id', 'status', 'episode_no']);
            $table->index(['drama_id', 'is_pay', 'episode_no']);
            $table->index('is_pay');
            $table->index('status');
        });

        // ─────────────────────────────────────────
        // 7. drama_tag（剧 × 标签）
        // ─────────────────────────────────────────
        Schema::create('drama_tag', function (Blueprint $table): void {
            $table->foreignId('drama_id')->constrained('dramas')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->unsignedBigInteger('created_at')->nullable();
            $table->primary(['drama_id', 'tag_id']);
            $table->index('tag_id');
        });

        // ─────────────────────────────────────────
        // 8. 重建 story_character_tag（解除对旧 tags 的外键）
        // ─────────────────────────────────────────
        if (Schema::hasTable('story_characters')) {
            Schema::create('story_character_tag', function (Blueprint $table): void {
                $table->foreignId('story_character_id')->constrained('story_characters')->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
                $table->primary(['story_character_id', 'tag_id']);
                $table->index('tag_id');
            });
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('story_character_tag');
        Schema::dropIfExists('drama_tag');
        Schema::dropIfExists('drama_episodes');
        Schema::dropIfExists('dramas');
        Schema::dropIfExists('tag_translations');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('languages');

        Schema::enableForeignKeyConstraints();
    }
};
