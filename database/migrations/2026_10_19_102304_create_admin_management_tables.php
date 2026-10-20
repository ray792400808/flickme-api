<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->json('permissions');
            $table->timestamps();
        });
        Schema::create('admin_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 254)->unique();
            $table->string('password')->nullable();
            $table->string('role_key');
            $table->string('status')->default('pending');
            $table->text('note')->nullable();
            $table->unsignedInteger('token_version')->default(1);
            $table->string('activation_hash', 64)->nullable();
            $table->timestamp('activation_expires_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('actor');
            $table->string('action');
            $table->string('subject');
            $table->timestamp('created_at')->useCurrent();
        });
        foreach (config('admin_permissions.roles') as $key => $role) {
            DB::table('admin_roles')->insert(['key' => $key, 'name' => $role['name'], 'permissions' => json_encode($role['permissions']), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('admin_accounts');
        Schema::dropIfExists('admin_roles');
    }
};
