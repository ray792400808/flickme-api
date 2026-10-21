<?php

namespace App\Http\Controllers\Admin;

use App\Common\ResponseDef;
use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Services\Admin\AdminRoleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminRoleController extends Controller
{
    public function __construct(private readonly AdminRoleService $roleService) {}

    public function index(Request $request)
    {
        $roles = $this->roleService->list($request->all());

        return $this->success($roles);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['required', 'string', 'max:50', 'unique:admin_roles,slug', 'regex:/^[a-z][a-z0-9_]*$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:admin_permissions,id'],
        ]);

        $this->roleService->create($data);

        return $this->success(['created' => true], '创建成功');
    }

    public function show(AdminRole $role)
    {
        return $this->success([
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'status' => $role->status,
            'is_default' => $role->is_default,
            'is_super_admin' => $role->isSuperAdmin(),
            'permission_ids' => $role->permissions()->pluck('admin_permissions.id')->all(),
            'users_count' => $role->users()->count(),
            'created_at' => $role->created_at->getTimestamp(),
        ]);
    }

    public function update(Request $request, AdminRole $role)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'slug' => ['sometimes', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('admin_roles', 'slug')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:admin_permissions,id'],
        ]);

        // 超级管理员角色不能停用
        if ($role->isSuperAdmin() && isset($data['status']) && ! $data['status']) {
            return $this->error(ResponseDef::CODE_SUPER_ADMIN_PROTECTED, ResponseDef::MSG_SUPER_ADMIN_PROTECTED, 422);
        }

        $updated = $this->roleService->update($role, $data);

        return $this->success($updated, '更新成功');
    }

    public function destroy(AdminRole $role)
    {
        $this->roleService->delete($role);

        return $this->success(['deleted' => true], '删除成功');
    }

    public function users(AdminRole $role)
    {
        return $this->success($role->users()
            ->get(['id', 'name', 'email', 'status', 'login_at'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'status' => $u->status,
                'login_at' => $u->login_at,
            ])->all());
    }
}