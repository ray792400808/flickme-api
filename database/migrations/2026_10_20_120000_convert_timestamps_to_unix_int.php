<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 将所有表的时间字段(created_at, updated_at, deleted_at)改为秒级时间戳(int类型)
     */
    public function up(): void
    {
        $tables = [
            // RBAC 相关表
            'admin_users',
            'admin_roles',
            'admin_permissions',
            'admin_menus',
            'admin_role_user',
            'admin_role_permission',
            'admin_role_menu',
            'admin_user_permission',
            'admin_operation_logs',
            // 业务表
            'users',
            'tags',
            'dramas',
            'story_characters',
        ];

        foreach ($tables as $table) {
            $this->convertTimestamps($table);
        }
    }

    public function down(): void
    {
        $tables = [
            'admin_users',
            'admin_roles',
            'admin_permissions',
            'admin_menus',
            'admin_role_user',
            'admin_role_permission',
            'admin_role_menu',
            'admin_user_permission',
            'admin_operation_logs',
            'users',
            'tags',
            'dramas',
            'story_characters',
        ];

        foreach ($tables as $table) {
            $this->revertTimestamps($table);
        }
    }

    private function convertTimestamps(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $this->convertField($blueprint, 'created_at', $table);
            $this->convertField($blueprint, 'updated_at', $table);
            $this->convertField($blueprint, 'deleted_at', $table);
        });
    }

    private function convertField(Blueprint $blueprint, string $field, string $table): void
    {
        if (! Schema::hasColumn($table, $field)) {
            return;
        }

        // 使用 bigInteger 存储秒级时间戳，保留 nullable 和 default 特性
        $blueprint->bigInteger($field)->nullable()->change();
    }

    private function revertTimestamps(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $this->revertField($blueprint, 'created_at', $table);
            $this->revertField($blueprint, 'updated_at', $table);
            $this->revertField($blueprint, 'deleted_at', $table);
        });
    }

    private function revertField(Blueprint $blueprint, string $field, string $table): void
    {
        if (! Schema::hasColumn($table, $field)) {
            return;
        }

        // 恢复为 timestamp 类型
        $blueprint->timestamp($field)->nullable()->change();
    }
};
