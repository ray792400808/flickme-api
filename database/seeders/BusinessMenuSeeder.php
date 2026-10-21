<?php

namespace Database\Seeders;

use App\Models\AdminMenu;
use App\Models\AdminPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $module = AdminPermission::query()->firstOrCreate(['code' => 'ops'], [
                'module' => 'ops', 'name' => '运营位', 'type' => AdminPermission::TYPE_MODULE,
            ]);
            foreach (['view' => '查看', 'edit' => '编辑', 'publish' => '发布'] as $action => $label) {
                AdminPermission::query()->firstOrCreate(['code' => 'ops.'.$action], [
                    'parent_id' => $module->id, 'module' => 'ops', 'name' => $label,
                    'type' => AdminPermission::TYPE_BUTTON,
                ]);
            }
            $groups = [
                ['运营位', '/operations', 'Operations', 'Grid', 20, [
                    ['首页分类', '/chips', '/operations/HomeCategoriesView', 'HomeCategories'],
                    ['热门池与排序', '/hot', '/operations/HotPoolView', 'HotPool'],
                    ['启动弹窗', '/launch', '/operations/LaunchPopupView', 'LaunchPopup'],
                    ['搜索推荐词', '/words', '/operations/SearchWordsView', 'SearchWords'],
                    ['Stories 筛选标签', '/filters', '/operations/StoryFiltersView', 'StoryFilters'],
                    ['推荐位配置', '/recs', '/operations/RecommendationsView', 'Recommendations'],
                ]],
                ['用户管理', '/users', 'BusinessUsers', 'User', 30, [
                    ['用户列表', '/userlist', '/users/UserListView', 'BusinessUserList'],
                    ['金币调整', '/coinadj', '/users/CoinAdjustmentsView', 'CoinAdjustments'],
                ]],
            ];

            foreach ($groups as [$title, $path, $name, $icon, $sort, $children]) {
                $parent = AdminMenu::query()->firstOrCreate(
                    ['path' => $path],
                    compact('title', 'name', 'icon', 'sort'),
                );

                foreach ($children as $index => [$childTitle, $childPath, $component, $childName]) {
                    AdminMenu::query()->firstOrCreate(['path' => $childPath], [
                        'parent_id' => $parent->id,
                        'title' => $childTitle,
                        'name' => $childName,
                        'component' => $component,
                        'sort' => ($index + 1) * 10,
                        'is_keep_alive' => true,
                    ]);
                }
            }
        });
    }
}
