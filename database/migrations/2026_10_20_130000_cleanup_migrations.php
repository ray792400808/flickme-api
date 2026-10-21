<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 清理迁移状态，确保迁移记录与数据库实际状态一致
     * 当迁移文件被修改后但数据库迁移记录已存在时使用
     */
    public function up(): void
    {
        // 确保 operation_configs 表存在（修复迁移文件修改后的问题）
        if (!Schema::hasTable('operation_configs')) {
            Schema::create('operation_configs', function (Blueprint $table): void {
                $table->id();
                $table->string('resource');
                $table->string('channel')->default('');
                $table->unsignedInteger('revision')->default(1);
                $table->unsignedInteger('published_revision')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->json('draft');
                $table->json('published')->nullable();
                $table->unique(['resource', 'channel']);
            });
        }

        // 确保 admin_idempotency_keys 表存在
        if (!Schema::hasTable('admin_idempotency_keys')) {
            Schema::create('admin_idempotency_keys', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('actor_id');
                $table->string('scope');
                $table->string('key_hash', 64);
                $table->string('body_hash', 64);
                $table->json('result')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['actor_id', 'scope', 'key_hash']);
            });
        }

        // 确保 coin_adjustments 表存在
        if (!Schema::hasTable('coin_adjustments')) {
            Schema::create('coin_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained();
                $table->string('uid');
                $table->string('currency');
                $table->bigInteger('amount');
                $table->string('reason', 500);
                $table->string('status')->default('pending');
                $table->unsignedBigInteger('requester_id');
                $table->string('requester_name');
                $table->unsignedBigInteger('reviewer_id')->nullable();
                $table->string('reviewer_name')->nullable();
                $table->string('review_reason', 500)->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->bigInteger('created_at')->nullable();
                $table->bigInteger('updated_at')->nullable();
            });
        }

        // 确保 wallet_entries 表存在
        if (!Schema::hasTable('wallet_entries')) {
            Schema::create('wallet_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained();
                $table->unsignedBigInteger('adjustment_id')->unique();
                $table->string('currency');
                $table->bigInteger('amount');
                $table->bigInteger('balance_after');
                $table->bigInteger('expires_at')->nullable();
                $table->bigInteger('created_at')->nullable();
            });
        }

        // 确保 tags, dramas, story_characters 表存在
        if (!Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 100)->unique();
                $table->string('name', 200);
                $table->string('status')->default('active')->index();
                $table->bigInteger('created_at')->nullable();
                $table->bigInteger('updated_at')->nullable();
                $table->bigInteger('deleted_at')->nullable();
            });
        }

        if (!Schema::hasTable('dramas')) {
            Schema::create('dramas', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 200);
                $table->text('synopsis')->nullable();
                $table->string('status')->default('draft')->index();
                $table->string('cover_url', 2048)->nullable();
                $table->string('video_url', 2048)->nullable();
                $table->boolean('is_playable')->default(false);
                $table->unsignedInteger('heat')->default(0);
                $table->bigInteger('created_at')->nullable();
                $table->bigInteger('updated_at')->nullable();
                $table->bigInteger('deleted_at')->nullable();
            });
        }

        if (!Schema::hasTable('story_characters')) {
            Schema::create('story_characters', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 200);
                $table->text('description')->nullable();
                $table->string('status')->default('draft')->index();
                $table->string('cover_url', 2048)->nullable();
                $table->bigInteger('created_at')->nullable();
                $table->bigInteger('updated_at')->nullable();
                $table->bigInteger('deleted_at')->nullable();
            });
        }

        // 修复 users 表的 uid 字段
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'uid') && !Schema::hasColumn('users', 'uid')) {
            // uid 字段不存在但需要添加
        }

        // 确保 admin_audit_logs 表存在 details 字段
        if (Schema::hasTable('admin_audit_logs') && !Schema::hasColumn('admin_audit_logs', 'details')) {
            Schema::table('admin_audit_logs', function (Blueprint $table): void {
                $table->json('details')->nullable();
            });
        }
    }

    public function down(): void
    {
        // 不删除任何表
    }
};
