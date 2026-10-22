<?php

namespace Database\Seeders;

use App\Models\AdminMenu;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * 后台 RBAC 初始数据。
 *
 * 权限码与 routes/admin.php 中的 CheckAdminPermission 参数严格对应，
 * 新增业务模块时同步补充 MODULES 定义即可。
 */
class AdminSeeder extends Seeder
{
    /** @var array<string, array{name:string, actions:string[]}> */
    private const MODULES = [
        'user' => ['name' => '用户管理', 'actions' => ['view', 'create', 'edit', 'delete']],
        'role' => ['name' => '角色管理', 'actions' => ['view', 'create', 'edit', 'delete']],
        'permission' => ['name' => '权限管理', 'actions' => ['view', 'create', 'edit', 'delete']],
        'menu' => ['name' => '菜单管理', 'actions' => ['view', 'create', 'edit', 'delete']],
        'log' => ['name' => '操作日志', 'actions' => ['view', 'delete']],
        'system' => ['name' => '系统设置', 'actions' => ['view']],
        'ops' => ['name' => '运营位', 'actions' => ['view', 'edit', 'publish']],
        'content' => ['name' => '内容管理', 'actions' => ['view', 'create', 'edit', 'delete']],
    ];

    private const ACTION_LABELS = [
        'view' => '查看',
        'create' => '创建',
        'edit' => '编辑',
        'delete' => '删除',
        'export' => '导出',
        'import' => '导入',
        'audit' => '审核',
    ];

    public function run(): void
    {
        $superAdmin = AdminRole::query()->firstOrCreate(
            ['slug' => 'super_admin'],
            ['name' => '超级管理员', 'description' => '拥有系统全部权限', 'status' => true]
        );

        $permissions = $this->seedPermissions();

        // 超级管理员绑定全部权限
        $superAdmin->permissions()->sync(collect($permissions)->pluck('id')->all());

        $this->seedRoles($permissions);
        $this->seedMenus($permissions);
        $this->seedUsers($superAdmin);
    }

    /**
     * 生成模块 + 动作两级权限树
     *
     * @return Collection<int, AdminPermission>
     */
    private function seedPermissions()
    {
        $result = collect();
        $moduleSort = 0;

        foreach (self::MODULES as $module => $meta) {
            $moduleSort += 10;
            $moduleNode = AdminPermission::query()->firstOrCreate(
                ['code' => $module],
                [
                    'name' => $meta['name'],
                    'module' => $module,
                    'type' => AdminPermission::TYPE_MODULE,
                    'sort' => $moduleSort,
                    'remark' => '模块根权限',
                ]
            );
            $result->push($moduleNode);

            $actionSort = 0;
            foreach ($meta['actions'] as $action) {
                $actionSort += 10;
                $result->push(AdminPermission::query()->firstOrCreate(
                    ['code' => "{$module}.{$action}"],
                    [
                        'parent_id' => $moduleNode->id,
                        'module' => $module,
                        'name' => self::ACTION_LABELS[$action] ?? $action,
                        'type' => AdminPermission::TYPE_BUTTON,
                        'sort' => $actionSort,
                    ]
                ));
            }
        }

        return $result;
    }

    private function seedRoles($allPermissions): void
    {
        $codes = fn (array $c) => $allPermissions->whereIn('code', $c)->pluck('id')->all();

        // 运营：内容相关，含用户只读
        $operator = AdminRole::query()->firstOrCreate(
            ['slug' => 'operator'],
            ['name' => '运营', 'description' => '日常运营，可查看用户', 'status' => true]
        );
        $operator->permissions()->sync($codes([
            'user.view', 'role.view', 'menu.view', 'log.view',
            'ops.view', 'ops.edit', 'ops.publish',
            'content.view', 'content.create', 'content.edit', 'content.delete',
        ]));

        // 客服：仅用户查看
        $service = AdminRole::query()->firstOrCreate(
            ['slug' => 'customer_service'],
            ['name' => '客服', 'description' => '处理用户咨询', 'status' => true]
        );
        $service->permissions()->sync($codes(['user.view']));

        // 财务：仅操作日志只读
        $finance = AdminRole::query()->firstOrCreate(
            ['slug' => 'finance'],
            ['name' => '财务', 'description' => '财务对账，查看审计日志', 'status' => true]
        );
        $finance->permissions()->sync($codes(['log.view']));
    }

    private function seedMenus($allPermissions): void
    {
        $byCode = $allPermissions->keyBy('code');

        $dashboard = AdminMenu::query()->firstOrCreate(
            ['path' => '/dashboard'],
            [
                'title' => '数据概览',
                'icon' => 'DataBoard',
                'name' => 'Dashboard',
                'component' => '/dashboard/index',
                'sort' => 10,
                'is_affix' => true,
            ]
        );

        // 系统管理为目录节点，无组件、无权限
        $system = AdminMenu::query()->firstOrCreate(
            ['path' => '/system'],
            [
                'title' => '系统管理',
                'icon' => 'Setting',
                'name' => 'System',
                'sort' => 90,
                'is_affix' => true,
            ]
        );

        $children = [
            ['code' => 'user.view', 'title' => '账户管理', 'path' => '/system/users', 'component' => '/system/users/index', 'icon' => 'User', 'name' => 'SystemUsers', 'sort' => 10],
            ['code' => 'role.view', 'title' => '角色管理', 'path' => '/system/roles', 'component' => '/system/roles/index', 'icon' => 'UserFilled', 'name' => 'SystemRoles', 'sort' => 20],
            ['code' => 'permission.view', 'title' => '权限管理', 'path' => '/system/permissions', 'component' => '/system/permissions/index', 'icon' => 'Lock', 'name' => 'SystemPermissions', 'sort' => 30],
            ['code' => 'menu.view', 'title' => '菜单管理', 'path' => '/system/menus', 'component' => '/system/menus/index', 'icon' => 'Menu', 'name' => 'SystemMenus', 'sort' => 40],
            ['code' => 'log.view', 'title' => '操作日志', 'path' => '/system/logs', 'component' => '/system/logs/index', 'icon' => 'List', 'name' => 'SystemLogs', 'sort' => 50],
        ];

        foreach ($children as $child) {
            $code = $child['code'];
            unset($child['code']);

            AdminMenu::query()->firstOrCreate(
                ['path' => $child['path']],
                array_merge([
                    'parent_id' => $system->id,
                    'permission_id' => $byCode->get($code)?->id,
                    'is_keep_alive' => true,
                ], $child)
            );
        }
    }

    private function seedUsers(AdminRole $superAdmin): void
    {
        $admin = AdminUser::query()->firstOrCreate(
            ['email' => 'admin@flickme.com'],
            [
                'name' => '超级管理员',
                'password' => Hash::make('admin123'),
                'status' => true,
            ]
        );

        if (! $admin->roles()->where('admin_roles.id', $superAdmin->id)->exists()) {
            $admin->roles()->attach($superAdmin->id);
        }
    }
}
