<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 内容管理四张表对齐到最终确认的表结构。
 *
 * 上一版迁移（2026_10_21_100000_create_dramas_tables）里 languages 额外带了
 * `is_default` 字段。最终表结构中语言没有"系统默认语种"这一列，默认语种由
 * seeder 写入的基础语种 + `sort` 顺序决定，因此这里删除该列，并补齐最终表结构
 * 里要求但上一版缺失的软删除索引。
 *
 * 幂等：字段已删除、索引已存在时跳过，便于本地库与全新库都能跑。
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> 表名 => [列名 => 索引名] */
    private const MISSING_INDEXES = [
        'languages' => ['deleted_at' => 'idx_languages_deleted_at'],
        'badges' => ['deleted_at' => 'idx_drama_badges_deleted_at'],
        'tags' => ['deleted_at' => 'idx_tags_deleted_at'],
        'drama_episodes' => ['deleted_at' => 'idx_drama_episodes_deleted_at'],
        'tag_translations' => ['deleted_at' => 'idx_tag_trans_deleted_at'],
    ];

    public function up(): void
    {
        if (Schema::hasTable('languages') && Schema::hasColumn('languages', 'is_default')) {
            // SQLite 不支持直接删除带索引的列，先把索引摘掉
            foreach (Schema::getIndexes('languages') as $index) {
                if (in_array('is_default', $index['columns'] ?? [], true)) {
                    Schema::table('languages', function (Blueprint $table) use ($index): void {
                        $table->dropIndex($index['name']);
                    });
                }
            }

            Schema::table('languages', function (Blueprint $table): void {
                $table->dropColumn('is_default');
            });
        }

        foreach (self::MISSING_INDEXES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = array_column(Schema::getIndexes($table), 'name');

            foreach ($columns as $column => $indexName) {
                if (! Schema::hasColumn($table, $column) || in_array($indexName, $existing, true)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column, $indexName): void {
                    $blueprint->index($column, $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('languages') && ! Schema::hasColumn('languages', 'is_default')) {
            Schema::table('languages', function (Blueprint $table): void {
                $table->tinyInteger('is_default')->unsigned()->default(0)
                    ->comment('是否系统默认语种；全局至多 1 行');
                $table->index('is_default');
            });
        }

        foreach (self::MISSING_INDEXES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = array_column(Schema::getIndexes($table), 'name');

            foreach ($columns as $indexName) {
                if (in_array($indexName, $existing, true)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                        $blueprint->dropIndex($indexName);
                    });
                }
            }
        }
    }
};
