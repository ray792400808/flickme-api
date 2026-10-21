<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminMenu;
use App\Models\AdminUser;
use App\Services\Admin\AdminMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function __construct(private readonly AdminMenuService $menuService) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $user = AdminUser::query()->where('email', strtolower($data['email']))->first();

        // 无论用户是否存在都执行一次 Hash::check，避免时序侧信道
        $valid = Hash::check($data['password'], $user?->password ?? '$2y$12$'.str_repeat('.', 53));

        if (! $user || ! $valid) {
            return response()->json(['message' => '邮箱或密码错误'], 401);
        }

        if (! $user->status) {
            return response()->json(['message' => '账号已被禁用，请联系超级管理员'], 403);
        }

        if (! $user->roles()->exists()) {
            return response()->json(['message' => '账号尚未分配角色，无法登录'], 403);
        }

        $token = $user->issueToken();
        $user->forceFill(['login_at' => time(), 'login_ip' => $request->ip()])->save();

        return response()->json(['data' => array_merge($token, ['user' => $this->profile($user)])]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profile($request->user()->fresh('roles'))]);
    }

    /**
     * 独立拉取菜单路由，前端可用于刷新侧边栏
     */
    public function menus(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->menuTree($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->revokeToken((string) $request->attributes->get('admin_token'));

        return response()->json(['data' => ['logged_out' => true]]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'old_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:64', 'confirmed'],
        ]);

        if (! Hash::check($data['old_password'], $request->user()->password)) {
            return response()->json(['message' => '原密码不正确'], 422);
        }

        $request->user()->forceFill(['password' => $data['password']])->save();
        $request->user()->bumpSessionEpoch();

        return response()->json(['data' => ['password_changed' => true]]);
    }

    /**
     * 刷新令牌，同时返回最新权限（权限变更后无需重新登录）
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $oldToken = (string) $request->attributes->get('admin_token');

        $token = $user->issueToken();
        $user->revokeToken($oldToken);

        return response()->json(['data' => array_merge($token, ['user' => $this->profile($user->fresh('roles'))])]);
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
            'login_at' => $user->login_at ? date('c', $user->login_at) : null,
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
