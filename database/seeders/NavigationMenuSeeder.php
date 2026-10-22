<?php

namespace Database\Seeders;

use App\Models\AdminMenu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NavigationMenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $groups = [
                ['/content', 'Short 内容管理', 'Content', 'Film', [
                    ['/content/dramas', 'Short 剧库', '/content/DramasView'],
                    ['/content/tags', 'Short 标签', '/content/TagsView'],
                    ['/content/badges', '角标管理', '/content/BadgesView'],
                    ['/chips', 'Short 首页分类 Chips', '/operations/HomeCategoriesView'],
                    ['/words', 'Short 搜索推荐词', '/operations/SearchWordsView'],
                    ['/hot', 'Short 热门池与排序', '/operations/HotPoolView'],
                ]],
                ['/stories', 'Stories 内容管理', 'Stories', 'Film', [
                    ['/characters', 'Stories 角色库', '/characters/index'],
                    ['/cands', 'AI 角色备选库', '/cands/CandidateListView'],
                    ['/filters', 'Stories 标签', '/operations/StoryFiltersView'],
                    ['/chipss', 'Stories 首页分类 Chips', null],
                    ['/swords', 'Stories 搜索推荐词', null],
                ]],
                ['/operations', '运营位', 'Operations', 'Grid', [
                    ['/launch', '启动弹窗', '/operations/LaunchPopupView'],
                    ['/recs', '推荐位配置', '/operations/RecommendationsView'],
                ]],
                ['/users', '用户管理', 'BusinessUsers', 'User', [
                    ['/userlist', '用户列表', '/users/UserListView'],
                    ['/coinadj', '金币调整', '/users/CoinAdjustmentsView'],
                ]],
                ['/commerce', '商业化', 'Commerce', 'Coin', [
                    ['/plans', '会员套餐', null],
                    ['/packs', '充值包', null],
                    ['/coupons', '优惠券', null],
                    ['/rules', '金币规则', null],
                    ['/orders', '订单与流水', null],
                ]],
                ['/ai', 'AI 配置', 'Ai', 'MagicStick', [
                    ['/models', '模型管理', null],
                    ['/quota', '聊天额度', null],
                    ['/memory', '记忆上限', null],
                    ['/safety', '内容安全', null],
                ]],
                ['/tasks', '激励任务', 'Tasks', 'Present', [
                    ['/checkin', '签到奖励', '/tasks/CheckinView'],
                    ['/taskcfg', '任务配置', '/tasks/TaskConfigView'],
                    ['/watch', '实时观看奖励', '/tasks/WatchRewardsView'],
                ]],
                ['/push', '消息与推送', 'Push', 'Bell', [
                    ['/pushtasks', '推送任务', null],
                    ['/pushdef', '通知默认与频控', null],
                ]],
                ['/service', '客服与反馈', 'Service', 'ChatDotSquare', [
                    ['/feedback', '反馈与举报工单', null],
                ]],
                ['/app', 'App 配置', 'AppSettings', 'Setting', [
                    ['/refresh', 'Home 刷新间隔', null],
                    ['/popups', '弹窗策略', null],
                    ['/risk', '风控规则', null],
                ]],
                ['/system', '系统设置', 'System', 'Setting', [
                    ['/system/users', '账号管理', '/system/users/index'],
                    ['/system/roles', '角色与权限', '/system/roles/index'],
                    ['/system/logs', '操作日志', '/system/logs/index'],
                    ['/legal', '协议管理', null],
                    ['/content/languages', '语言管理', '/content/LanguagesView'],
                    ['/system/permissions', '权限管理', '/system/permissions/index'],
                    ['/system/menus', '菜单管理', '/system/menus/index'],
                ]],
            ];

            foreach ($groups as $groupIndex => [$path, $title, $name, $icon, $children]) {
                $parent = AdminMenu::query()->updateOrCreate(['path' => $path], [
                    'parent_id' => null,
                    'title' => $title,
                    'name' => $name,
                    'icon' => $icon,
                    'component' => null,
                    'sort' => ($groupIndex + 2) * 10,
                ]);

                foreach ($children as $childIndex => [$childPath, $childTitle, $component]) {
                    $menu = AdminMenu::query()->firstOrNew(['path' => $childPath]);
                    if (! $menu->exists) {
                        $menu->name = $name.ucfirst(basename($childPath));
                        $menu->is_keep_alive = true;
                    }

                    $menu->fill([
                        'parent_id' => $parent->id,
                        'title' => $childTitle,
                        'component' => $component ?? '/system/ModulePendingView',
                        'sort' => ($childIndex + 1) * 10,
                    ])->save();
                }
            }
        });
    }
}
