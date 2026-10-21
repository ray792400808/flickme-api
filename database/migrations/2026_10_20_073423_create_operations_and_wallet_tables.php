<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'uid')) {
                $table->string('uid')->nullable()->unique();
            }
            if (!Schema::hasColumn('users', 'login_provider')) {
                $table->string('login_provider')->default('email');
            }
            if (!Schema::hasColumn('users', 'member_coins')) {
                $table->bigInteger('member_coins')->default(0);
            }
            if (!Schema::hasColumn('users', 'bonus')) {
                $table->bigInteger('bonus')->default(0);
            }
            if (!Schema::hasColumn('users', 'membership')) {
                $table->string('membership')->default('none');
            }
            if (!Schema::hasColumn('users', 'unlocked_episodes')) {
                $table->unsignedInteger('unlocked_episodes')->default(0);
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active');
            }
            if (!Schema::hasColumn('users', 'ban_reason')) {
                $table->string('ban_reason', 500)->nullable();
            }
            if (!Schema::hasColumn('users', 'version')) {
                $table->unsignedInteger('version')->default(1);
            }
        });
        DB::table('users')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update(['uid' => (string) $user->id]);
        });
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
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('wallet_entries')) {
            Schema::create('wallet_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained();
                $table->foreignId('adjustment_id')->unique()->constrained('coin_adjustments');
                $table->string('currency');
                $table->bigInteger('amount');
                $table->bigInteger('balance_after');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
        if (Schema::hasTable('admin_audit_logs') && !Schema::hasColumn('admin_audit_logs', 'details')) {
            Schema::table('admin_audit_logs', function (Blueprint $table): void {
                $table->json('details')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', fn (Blueprint $table) => $table->dropColumn('details'));
        foreach (['wallet_entries', 'coin_adjustments', 'admin_idempotency_keys', 'operation_configs'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['uid', 'login_provider', 'member_coins', 'bonus', 'membership', 'unlocked_episodes', 'status', 'ban_reason', 'version']));
    }
};
