<?php

namespace Database\Seeders;

use App\Models\AdminMenu;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 内容管理模块的权限与菜单。
 *
 * 覆盖原型「内容管理」下的四个页面：
 *   - Short 剧库（剧集 + 分集）
 *   - 标签与合集
 *   - 角标
 *   - 语言列表
 *
 * 权限码遵循 module.action 约定：content.view / content.create / content.edit / content.delete，
 * 与 routes/admin.php 中 CheckAdminPermission 参数一一对应。
 */
class ContentMenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $module = AdminPermission::query()->firstOrCreate(['code' => 'content'], [
                'module' => 'content',
                'name' => '内容管理',
                'type' => AdminPermission::TYPE_MODULE,
                'sort' => 15,
            ]);

            foreach (['view' => '查看', 'create' => '创建', 'edit' => '编辑', 'delete' => '删除'] as $action => $label) {
                AdminPermission::query()->firstOrCreate(['code' => 'content.'.$action], [
                    'parent_id' => $module->id,
                    'module' => 'content',
                    'name' => $label,
                    'type' => AdminPermission::TYPE_BUTTON,
                ]);
            }

            $permissions = AdminPermission::query()->pluck('id', 'code');

            // 内容管理目录节点（本身无组件、无权限）
            $parent = AdminMenu::query()->firstOrCreate(['path' => '/content'], [
                'title' => '内容管理',
                'name' => 'Content',
                'icon' => 'Film',
                'sort' => 20,
                'is_affix' => true,
            ]);

            $children = [
                ['title' => 'Short 剧库', 'path' => '/content/dramas', 'component' => '/content/DramasView', 'name' => 'ContentDramas', 'sort' => 10],
                ['title' => '标签与合集', 'path' => '/content/tags', 'component' => '/content/TagsView', 'name' => 'ContentTags', 'sort' => 20],
                ['title' => '角标', 'path' => '/content/badges', 'component' => '/content/BadgesView', 'name' => 'ContentBadges', 'sort' => 30],
                ['title' => '语言列表', 'path' => '/content/languages', 'component' => '/content/LanguagesView', 'name' => 'ContentLanguages', 'sort' => 40],
            ];

            foreach ($children as $child) {
                AdminMenu::query()->firstOrCreate(
                    ['path' => $child['path']],
                    array_merge($child, [
                        'parent_id' => $parent->id,
                        'permission_id' => $permissions->get('content.view'),
                        'is_keep_alive' => true,
                    ])
                );
            }

            // 运营角色获得内容管理读写权限
            $operator = AdminRole::query()->where('slug', 'operator')->first();
            if ($operator) {
                $contentIds = $permissions->only([
                    'content', 'content.view', 'content.create', 'content.edit', 'content.delete',
                ])->values()->all();

                $operator->permissions()->syncWithoutDetaching($contentIds);

                // 菜单也要挂到运营角色，否则登录后看不到入口
                $menuIds = AdminMenu::query()
                    ->whereIn('path', array_column($children, 'path'))
                    ->pluck('id')
                    ->all();

                $operator->menus()->syncWithoutDetaching($menuIds);
            }
        });
    }
}
