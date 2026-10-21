<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class ColumnCommentsMigrationTest extends TestCase
{
    public function test_comments_preserve_column_definitions_and_existing_comments(): void
    {
        DB::shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('getDatabaseName')->andReturn('test');
        DB::shouldReceive('getPdo')->andReturn(new PDO('sqlite::memory:'));
        DB::shouldReceive('select')->andReturnUsing(function (string $query, array $bindings = []): array {
            if ($query === 'SHOW CREATE TABLE `tags`') {
                return [(object) ['Create Table' => "CREATE TABLE `tags` (\n  `id` bigint unsigned NOT NULL AUTO_INCREMENT,\n  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',\n  `name` varchar(200) NOT NULL COMMENT '人工维护的标签名称',\n  PRIMARY KEY (`id`)\n)"]];
            }

            return ($bindings[1] ?? null) === 'tags' ? [
                (object) ['COLUMN_NAME' => 'id', 'COLUMN_COMMENT' => ''],
                (object) ['COLUMN_NAME' => 'key', 'COLUMN_COMMENT' => ''],
                (object) ['COLUMN_NAME' => 'name', 'COLUMN_COMMENT' => '人工维护的标签名称'],
            ] : [];
        });
        DB::shouldReceive('statement')->once()->with("ALTER TABLE `tags` MODIFY COLUMN `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID', MODIFY COLUMN `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '标签唯一业务标识'")->andReturn(true);
        $migration = require database_path('migrations/2026_10_21_083127_add_missing_column_comments_to_project_tables.php');
        $migration->up();
    }

    public function test_non_mysql_database_is_not_modified(): void
    {
        DB::shouldReceive('getDriverName')->once()->andReturn('sqlite');
        DB::shouldReceive('statement')->never();
        $migration = require database_path('migrations/2026_10_21_083127_add_missing_column_comments_to_project_tables.php');
        $migration->up();
    }

    public function test_unknown_fields_fail_instead_of_guessing_their_meaning(): void
    {
        DB::shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('getDatabaseName')->andReturn('test');
        DB::shouldReceive('select')->andReturnUsing(function (string $query, array $bindings = []): array {
            if ($query === 'SHOW CREATE TABLE `tags`') {
                return [(object) ['Create Table' => "CREATE TABLE `tags` (\n  `unknown_field` int DEFAULT NULL\n)"]];
            }

            return ($bindings[1] ?? null) === 'tags' ? [(object) ['COLUMN_NAME' => 'unknown_field', 'COLUMN_COMMENT' => '']] : [];
        });
        DB::shouldReceive('statement')->never();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('缺少字段注释定义：tags.unknown_field');
        $migration = require database_path('migrations/2026_10_21_083127_add_missing_column_comments_to_project_tables.php');
        $migration->up();
    }
}
