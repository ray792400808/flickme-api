<?php

namespace App\Http\Controllers\Admin;

use App\Common\ResponseDef;
use App\Http\Controllers\Controller;
use App\Models\AdminMenu;
use App\Models\AdminUser;
use App\Services\Admin\AdminMenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function __construct(private readonly AdminMenuService $menuService) {}

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $user = AdminUser::query()->where('email', strtolower($data['email']))->first();

        // 无论用户是否存在都执行一次 Hash::check，避免时序侧信道
        $valid = Hash::check($data['password'], $user?->password ?? '$2y$12$'.str_repeat('.', 53));

        if (! $user || ! $valid) {
            return $this->error(ResponseDef::CODE_LOGIN_FAILED, ResponseDef::MSG_LOGIN_FAILED, 401);
        }

        if (! $user->status) {
            return $this->error(ResponseDef::CODE_ACCOUNT_DISABLED, ResponseDef::MSG_ACCOUNT_DISABLED, 403);
        }

        if (! $user->roles()->exists()) {
            return $this->error(ResponseDef::CODE_ACCOUNT_NOT_ROLED, ResponseDef::MSG_ACCOUNT_NOT_ROLED, 403);
        }

        $token = $user->issueToken();
        $user->forceFill(['login_at' => time(), 'login_ip' => $request->ip()])->save();

        return $this->success(array_merge($token, ['user' => $this->profile($user)]), '登录成功');
    }

    public function me(Request $request)
    {
        return $this->success($this->profile($request->user()->fresh('roles')));
    }

    /**
     * 独立拉取菜单路由，前端可用于刷新侧边栏
     */
    public function menus(Request $request)
    {
        return $this->success($this->menuTree($request->user()));
    }

    public function logout(Request $request)
    {
        $request->user()->revokeToken((string) $request->attributes->get('admin_token'));

        return $this->success(['logged_out' => true], '已退出登录');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'old_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:64', 'confirmed'],
        ]);

        if (! Hash::check($data['old_password'], $request->user()->password)) {
            return $this->error(ResponseDef::CODE_OLD_PASSWORD_ERROR, ResponseDef::MSG_OLD_PASSWORD_ERROR, 422);
        }

        $request->user()->forceFill(['password' => $data['password']])->save();
        $request->user()->bumpSessionEpoch();

        return $this->success(['password_changed' => true], '密码已修改');
    }

    /**
     * 刷新令牌，同时返回最新权限（权限变更后无需重新登录）
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $oldToken = (string) $request->attributes->get('admin_token');

        $token = $user->issueToken();
        $user->revokeToken($oldToken);

        return $this->success(array_merge($token, ['user' => $this->profile($user->fresh('roles'))]));
    }

    // ─── 内部 ───────────────────────────────────────────────

    private function profile(AdminUser $user): array
    {
        $roles = $user->roles;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'is_super_admin' => $user->isSuperAdmin(),
            'roles' => $roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])->all(),
            'permissions' => $user->cachedPermissionCodes(),
            'menus' => $this->menuTree($user),
            // Unix 秒（秒级），与列表接口保持一致
            'login_at' => $user->login_at ? (int) $user->login_at : null,
        ];
    }

    /**
     * 按权限过滤后的菜单树。
     * 无 permission_id 的目录节点自动保留（有子节点才显示），
     * 保证父级不会因自身无权限而消失。
     */
    private function menuTree(AdminUser $user): array
    {
        $allowed = $user->accessibleMenuIds();

        $menus = AdminMenu::query()
            ->when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed))
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        if ($menus->isEmpty()) {
            return [];
        }

        return $this->buildTree($menus->groupBy(fn ($m) => $m->parent_id ?? 0)->all(), 0);
    }

    private function buildTree(array $grouped, int $parentId): array
    {
        $branch = $grouped[$parentId] ?? collect();
        $tree = [];

        foreach ($branch as $menu) {
            $node = $menu->toRouteTree();

            // 目录节点：递归后有可见子节点才保留
            $children = $this->buildTree($grouped, $menu->id);
            if ($children !== []) {
                $node['children'] = $children;
            } elseif (($node['component'] ?? null) === null) {
                continue;
            }

            $tree[] = $node;
        }

        return $tree;
    }
}
