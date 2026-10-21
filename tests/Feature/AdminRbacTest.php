<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminSeeder::class);
    }

    // ---------------- 认证 ----------------

    public function test_super_admin_can_login_and_receives_profile(): void
    {
        $response = $this->postJson('/admin/v1/auth/login', [
            'email' => 'admin@flickme.com',
            'password' => 'admin123',
        ])->assertOk();

        $this->assertSame('超级管理员', $response->json('data.user.name'));
        $this->assertTrue($response->json('data.user.is_super_admin'));
        $this->assertContains('user.create', $response->json('data.user.permissions'));
    }

    public function test_login_rejects_wrong_password_and_disabled_account(): void
    {
        $this->postJson('/admin/v1/auth/login', [
            'email' => 'admin@flickme.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $admin = AdminUser::query()->where('email', 'admin@flickme.com')->firstOrFail();
        $admin->update(['status' => false]);

        $this->postJson('/admin/v1/auth/login', [
            'email' => 'admin@flickme.com',
            'password' => 'admin123',
        ])->assertForbidden();
    }

    public function test_request_without_token_is_rejected(): void
    {
        $this->getJson('/admin/v1/auth/me')->assertUnauthorized();
    }

    // ---------------- 按钮级权限 ----------------

    public function test_role_without_permission_cannot_create_user(): void
    {
        $user = $this->makeUser('viewer', []);

        $this->withHeaders($this->headersFor($user))
            ->postJson('/admin/v1/users', [
                'name' => 'New',
                'email' => 'new@example.com',
                'password' => 'password-123',
                'role_ids' => [$this->roleId('operator')],
            ])
            ->assertForbidden();
    }

    public function test_permission_grant_unlocks_endpoint(): void
    {
        $role = $this->role('viewer');
        $role->permissions()->sync([$this->permId('user.create')]);
        $user = $this->makeUser('viewer', [$role->id]);

        $this->withHeaders($this->headersFor($user))
            ->postJson('/admin/v1/users', [
                'name' => 'New',
                'email' => 'new@example.com',
                'password' => 'password-123',
                'role_ids' => [$this->roleId('operator')],
            ])
            ->assertCreated();
    }

    public function test_module_wildcard_permission_grants_all_actions(): void
    {
        $user = $this->makeUser('wildcard', [$this->roleId('operator')]);
        $user->permissions()->sync([$this->permId('role')]);

        // role 模块根权限应放行 role.view
        $this->withHeaders($this->headersFor($user))
            ->getJson('/admin/v1/roles')
            ->assertOk();
    }

    public function test_user_level_permission_grants_extra_access(): void
    {
        $user = $this->makeUser('auditor', [$this->roleId('operator')]);
        $user->permissions()->sync([$this->permId('permission.view')]);

        $this->withHeaders($this->headersFor($user))
            ->getJson('/admin/v1/permissions')
            ->assertOk();
    }

    // ---------------- 菜单过滤 ----------------

    public function test_menu_tree_is_filtered_by_permission(): void
    {
        $user = $this->makeUser('limited', [$this->roleId('operator')]);

        $me = $this->withHeaders($this->headersFor($user))
            ->getJson('/admin/v1/auth/me')
            ->assertOk();

        $paths = collect($me->json('data.menus'))
            ->flatMap(fn ($node) => array_merge([$node['path']], collect($node['children'] ?? [])->pluck('path')->all()))
            ->all();

        // operator 角色拥有 user.view / role.view / menu.view / log.view
        $this->assertContains('/system/users', $paths);
        $this->assertNotContains('/system/permissions', $paths);
    }

    public function test_directory_without_visible_children_is_dropped(): void
    {
        $user = $this->makeUser('nobody', [$this->roleId('customer_service')]);

        $me = $this->withHeaders($this->headersFor($user))
            ->getJson('/admin/v1/auth/me')
            ->assertOk();

        $titles = collect($me->json('data.menus'))->pluck('title');
        $this->assertFalse($titles->contains('系统管理'), '无可访问子节点时目录不应出现');
    }

    // ---------------- 软删除 ----------------

    public function test_soft_deleted_user_cannot_login(): void
    {
        $user = $this->makeUser('temp', [$this->roleId('operator')]);
        $user->delete();

        $this->postJson('/admin/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password-123',
        ])->assertUnauthorized();
    }

    public function test_soft_deleted_rows_are_excluded_by_default(): void
    {
        $user = $this->makeUser('gone', [$this->roleId('operator')]);
        $user->delete();

        $this->assertSame(1, AdminUser::query()->count());
        $this->assertSame(2, AdminUser::withTrashed()->count());
        $this->assertTrue(AdminUser::withTrashed()->whereKey($user->id)->exists());
    }

    // ---------------- 超级管理员保护 ----------------

    public function test_last_super_admin_cannot_be_deleted_or_demoted(): void
    {
        $admin = AdminUser::query()->where('email', 'admin@flickme.com')->firstOrFail();
        $headers = $this->headersFor($admin);

        $this->withHeaders($headers)
            ->patchJson('/admin/v1/users/'.$admin->id, ['status' => false])
            ->assertUnprocessable();

        $this->withHeaders($headers)
            ->deleteJson('/admin/v1/users/'.$admin->id)
            ->assertUnprocessable();

        $this->withHeaders($headers)
            ->patchJson('/admin/v1/users/'.$admin->id, ['role_ids' => [$this->roleId('operator')]])
            ->assertUnprocessable();
    }

    public function test_super_admin_role_is_immutable(): void
    {
        $headers = $this->headersFor(AdminUser::query()->where('email', 'admin@flickme.com')->firstOrFail());
        $superRoleId = $this->roleId('super_admin');

        $this->withHeaders($headers)
            ->patchJson('/admin/v1/roles/'.$superRoleId, ['slug' => 'hacked', 'permission_ids' => []])
            ->assertOk();

        $this->assertSame('super_admin', AdminRole::findOrFail($superRoleId)->slug);
        $this->assertTrue(AdminRole::findOrFail($superRoleId)->permissions()->exists());
    }

    // ---------------- 会话失效 ----------------

    public function test_role_change_revokes_existing_session(): void
    {
        $user = $this->makeUser('movable', [$this->roleId('operator')]);
        $headers = $this->headersFor($user);

        $this->getJson('/admin/v1/auth/me', $headers)->assertOk();

        $admin = AdminUser::query()->where('email', 'admin@flickme.com')->firstOrFail();
        $this->withHeaders($this->headersFor($admin))
            ->patchJson('/admin/v1/users/'.$user->id, ['role_ids' => [$this->roleId('finance')]])
            ->assertOk();

        $this->getJson('/admin/v1/auth/me', $headers)->assertUnauthorized();
    }

    public function test_password_reset_revokes_existing_session(): void
    {
        $user = $this->makeUser('resetme', [$this->roleId('operator')]);
        $headers = $this->headersFor($user);
        $admin = AdminUser::query()->where('email', 'admin@flickme.com')->firstOrFail();

        $this->withHeaders($this->headersFor($admin))
            ->postJson('/admin/v1/users/'.$user->id.'/reset-password', ['password' => 'brand-new-pass'])
            ->assertOk();

        $this->getJson('/admin/v1/auth/me', $headers)->assertUnauthorized();
    }

    public function test_refresh_token_issues_new_valid_token(): void
    {
        $user = $this->makeUser('refresher', [$this->roleId('super_admin')]);
        $headers = $this->headersFor($user);

        $response = $this->withHeaders($headers)->postJson('/admin/v1/auth/refresh')->assertOk();
        $newToken = $response->json('data.token');

        $this->getJson('/admin/v1/auth/me', $headers)->assertUnauthorized();
        $this->getJson('/admin/v1/auth/me', $this->bearer($newToken))->assertOk();
    }

    // ---------------- 日志 ----------------

    public function test_write_operation_is_logged(): void
    {
        $admin = AdminUser::query()->where('email', 'admin@flickme.com')->firstOrFail();
        $headers = $this->headersFor($admin);

        $this->withHeaders($headers)
            ->postJson('/admin/v1/users', [
                'name' => 'Logged',
                'email' => 'logged@example.com',
                'password' => 'password-123',
                'role_ids' => [$this->roleId('operator')],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('admin_operation_logs', [
            'admin_user_id' => $admin->id,
            'path' => 'admin/v1/users',
        ]);
    }

    // ---------------- 辅助 ----------------

    private function makeUser(string $email, array $roleIds): AdminUser
    {
        $user = AdminUser::query()->create([
            'name' => $email,
            'email' => $email.'@example.com',
            'password' => 'password-123',
            'status' => true,
        ]);
        $user->roles()->sync($roleIds);

        return $user->fresh('roles');
    }

    private function role(string $slug): AdminRole
    {
        return AdminRole::query()->where('slug', $slug)->firstOrFail();
    }

    private function roleId(string $slug): int
    {
        return $this->role($slug)->id;
    }

    private function permId(string $code): int
    {
        return AdminPermission::query()->where('code', $code)->firstOrFail()->id;
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    private function headersFor(AdminUser $user): array
    {
        $token = bin2hex(random_bytes(32));
        cache()->put($user->tokenKey($token), [
            'id' => $user->id,
            'version' => $user->tokenVersion(),
        ], now()->addHour());

        return $this->bearer($token);
    }
}