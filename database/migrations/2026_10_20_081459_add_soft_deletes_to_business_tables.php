<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'admin_accounts', 'tags', 'dramas', 'story_characters'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->softDeletes()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'admin_accounts', 'tags', 'dramas', 'story_characters'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            });
        }
    }
};
