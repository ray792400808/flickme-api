<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============ 1. 后台用户表 ============
        Schema::create('admin_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->comment('姓名');
            $table->string('email', 100)->unique()->comment('邮箱');
            $table->string('password')->comment('密码');
            $table->string('avatar', 500)->nullable()->comment('头像');
            $table->boolean('status')->default(true)->comment('状态:1启用,0禁用');
            $table->string('login_ip', 50)->nullable()->comment('最后登录IP');
            $table->bigInteger('login_at')->nullable()->comment('最后登录时间(Unix时间戳)');
            $table->rememberToken();
            // created_at, updated_at 使用 bigInteger
            $table->bigInteger('created_at')->nullable();
            $table->bigInteger('updated_at')->nullable();
            // deleted_at 使用 bigInteger，默认 null
            $table->bigInteger('deleted_at')->nullable();

            $table->index('status');
            $table->index('deleted_at');
        });

        // ============ 2. 角色表 ============
        Schema::create('admin_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->comment('角色名称');
            $table->string('slug', 50)->unique()->comment('角色标识');
            $table->string('description', 255)->nullable()->comment('角色描述');
            $table->boolean('status')->default(true)->comment('状态:1启用,0禁用');
            $table->boolean('is_default')->default(false)->comment('是否默认角色');
            // created_at, updated_at 使用 bigInteger
            $table->bigInteger('created_at')->nullable();
            $table->bigInteger('updated_at')->nullable();
            // deleted_at 使用 bigInteger，默认 null
            $table->bigInteger('deleted_at')->nullable();

            $table->index('status');
            $table->index('deleted_at');
        });

        // ============ 3. 权限表（按钮 / API 级别）============
        Schema::create('admin_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('admin_permissions')->nullOnDelete()->comment('父级权限');
            $table->string('module', 50)->comment('模块标识,如 user');
            $table->string('name', 50)->comment('权限名称,如 创建用户');
            $table->string('code', 100)->unique()->comment('权限标识,如 user.create');
            $table->unsignedTinyInteger('type')->default(3)->comment('类型:1模块,2页面,3按钮/API');
            $table->unsignedInteger('sort')->default(0)->comment('排序');
            $table->string('remark', 255)->nullable()->comment('备注');
            // created_at, updated_at 使用 bigInteger
            $table->bigInteger('created_at')->nullable();
            $table->bigInteger('updated_at')->nullable();
            // deleted_at 使用 bigInteger，默认 null
            $table->bigInteger('deleted_at')->nullable();

            $table->index(['module', 'type']);
            $table->index('deleted_at');
        });

        // ============ 4. 菜单表（页面 / 路由级）============
        Schema::create('admin_menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('admin_menus')->nullOnDelete()->comment('父级菜单');
            $table->foreignId('permission_id')->nullable()->constrained('admin_permissions')->nullOnDelete()->comment('访问该页面所需权限');
            $table->unsignedInteger('sort')->default(0)->comment('排序');
            $table->string('title', 50)->comment('菜单标题');
            $table->string('icon', 100)->nullable()->comment('图标');
            $table->string('path', 191)->comment('路由路径');
            $table->string('name', 100)->nullable()->comment('路由名称');
            $table->string('component', 255)->nullable()->comment('组件路径');
            $table->string('redirect', 255)->nullable()->comment('重定向');
            $table->boolean('is_hidden')->default(false)->comment('是否隐藏');
            $table->boolean('is_affix')->default(false)->comment('是否固定');
            $table->boolean('is_keep_alive')->default(true)->comment('是否缓存');
            $table->json('meta')->nullable()->comment('额外元数据');
            // created_at, updated_at 使用 bigInteger
            $table->bigInteger('created_at')->nullable();
            $table->bigInteger('updated_at')->nullable();
            // deleted_at 使用 bigInteger，默认 null
            $table->bigInteger('deleted_at')->nullable();

            $table->index(['parent_id', 'sort']);
            $table->index('deleted_at');
        });

        // ============ 5. 用户-角色关联 ============
        Schema::create('admin_role_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            // created_at, updated_at 使用 bigInteger
            $table->bigInteger('created_at')->nullable();
            $table->bigInteger('updated_at')->nullable();
            // deleted_at 使用 bigInteger，默认 null
            $table->bigInteger('deleted_at')->nullable();

            $table->index(['admin_user_id', 'deleted_at']);
            $table->index(['admin_role_id', 'deleted_at']);
        });

        // ============ 6. 角色-权限关联 ============
        Schema::create('admin_role_permission', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->foreignId('admin_permission_id')->constrained('admin_permissions')->cascadeOnDelete();
            // created_at, updated_at 使用 bigInteger
            $table->bigInteger('created_at')->nullable();
            $table->bigInteger('updated_at')->nullable();
            // deleted_at 使用 bigInteger，默认 null
            $table->bigInteger('deleted_at')->nullable();

            $table->index(['admin_role_id', 'deleted_at']);
            $table->index(['admin_permission_id', 'deleted_at']);
        });

        // ============ 7. 角色-菜单关联 ============
        Schema::create('admin_role_menu', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->foreignId('admin_menu_id')->constrained('admin_menus')->cascadeOnDelete();
            // created_at, updated_at 使用 bigInteger
            $table->bigInteger('created_at')->nullable();
            $table->bigInteger('updated_at')->nullable();
            // deleted_at 使用 bigInteger，默认 null
            $table->bigInteger('deleted_at')->nullable();

            $table->index(['admin_role_id', 'deleted_at']);
            $table->index(['admin_menu_id', 'deleted_at']);
        });

        // ============ 8. 用户-权限关联（角色之外的额外授权）============
        Schema::create('admin_user_permission', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->foreignId('admin_permission_id')->constrained('admin_permissions')->cascadeOnDelete();
            // created_at, updated_at 使用 bigInteger
            $table->bigInteger('created_at')->nullable();
            $table->bigInteger('updated_at')->nullable();
            // deleted_at 使用 bigInteger，默认 null
            $table->bigInteger('deleted_at')->nullable();

            $table->index(['admin_user_id', 'deleted_at']);
            $table->index(['admin_permission_id', 'deleted_at']);
        });

        // ============ 9. 操作日志表 ============
        Schema::create('admin_operation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('username', 50)->comment('操作用户');
            $table->string('method', 10)->comment('请求方法');
            $table->string('path', 255)->comment('请求路径');
            $table->string('ip', 50)->nullable()->comment('IP地址');
            $table->json('input')->nullable()->comment('请求参数');
            $table->string('description', 255)->nullable()->comment('操作描述');
            $table->string('trace_id', 64)->nullable()->comment('请求追踪ID');
            // created_at, updated_at 使用 bigInteger
            $table->bigInteger('created_at')->nullable();
            $table->bigInteger('updated_at')->nullable();
            // deleted_at 使用 bigInteger，默认 null
            $table->bigInteger('deleted_at')->nullable();

            $table->index(['admin_user_id', 'created_at']);
            $table->index('created_at');
            $table->index('deleted_at');
            $table->index('trace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_operation_logs');
        Schema::dropIfExists('admin_user_permission');
        Schema::dropIfExists('admin_role_menu');
        Schema::dropIfExists('admin_role_permission');
        Schema::dropIfExists('admin_role_user');
        Schema::dropIfExists('admin_menus');
        Schema::dropIfExists('admin_permissions');
        Schema::dropIfExists('admin_roles');
        Schema::dropIfExists('admin_users');
    }
};
