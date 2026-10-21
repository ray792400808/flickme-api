<?php

namespace App\Http\Controllers\Admin;

use App\Common\ResponseDef;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\AdminUserService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserService $userService,
    ) {}

    public function index(Request $request)
    {
        $users = $this->userService->list($request->all());

        return $this->success($users);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:100', 'unique:admin_users,email'],
            'password' => ['required', 'string', 'min:8', 'max:64'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:admin_roles,id'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:admin_permissions,id'],
            'status' => ['nullable', 'boolean'],
        ]);

        $user = $this->userService->create($data);

        return $this->success(['created' => true], '创建成功');
    }

    public function show(AdminUser $user)
    {
        $user->load(['roles:id,name,slug', 'permissions:id,name,code']);

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'status' => $user->status,
            'role_ids' => $user->roles->pluck('id')->all(),
            'permission_ids' => $user->permissions->pluck('id')->all(),
            'roles' => $user->roles,
            'login_at' => $user->login_at,
            'login_ip' => $user->login_ip,
            'created_at' => $user->created_at->getTimestamp(),
        ]);
    }

    public function update(Request $request, AdminUser $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'email' => ['sometimes', 'email', 'max:100', Rule::unique('admin_users,email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:64'],
            'status' => ['sometimes', 'boolean'],
            'role_ids' => ['sometimes', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:admin_roles,id'],
            'permission_ids' => ['sometimes', 'nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:admin_permissions,id'],
        ]);
        $operator = $request->user();

        if ($operator->is($user)) {
            if (isset($data['status']) && ! $data['status']) {
                return $this->error(ResponseDef::CODE_CANNOT_OPERATE_SELF, '不能停用当前登录的账号', 422);
            }
            if (isset($data['role_ids']) && ! $this->userService->stillSuperAdminAfter($user, $data['role_ids'])) {
                return $this->error(ResponseDef::CODE_SUPER_ADMIN_PROTECTED, '不能移除自己的超级管理员角色', 422);
            }
        }

        $updated = $this->userService->update($user, $data);

        return $this->success($updated, '更新成功');
    }

    public function destroy(Request $request, AdminUser $user)
    {
        $this->userService->delete($user, $request->user());

        return $this->success(['deleted' => true], '删除成功');
    }

    public function forceLogout(Request $request, AdminUser $user)
    {
        $this->userService->forceLogout($user, $request->user());

        return $this->success(['logged_out' => true], '已强制下线');
    }

    public function resetPassword(Request $request, AdminUser $user)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:64'],
        ]);

        $this->userService->resetPassword($user, $data['password'], $request->user());

        return $this->success(['reset' => true], '密码已重置');
    }
}