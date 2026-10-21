<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 将 login_at 从 timestamp 改为 bigint (unix timestamp)
        if (Schema::hasTable('admin_users') && Schema::hasColumn('admin_users', 'login_at')) {
            Schema::table('admin_users', function (Blueprint $table): void {
                $table->bigInteger('login_at')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_users') && Schema::hasColumn('admin_users', 'login_at')) {
            Schema::table('admin_users', function (Blueprint $table): void {
                $table->timestamp('login_at')->nullable()->change();
            });
        }
    }
};
