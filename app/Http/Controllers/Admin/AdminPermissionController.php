<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Services\Admin\AdminPermissionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPermissionController extends Controller
{
    public function __construct(private readonly AdminPermissionService $permService) {}

    public function index(Request $request)
    {
        return $this->success($this->permService->list($request->all()));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:admin_permissions,id'],
            'name' => ['required', 'string', 'max:50'],
            'code' => [
                'required', 'string', 'max:100', 'unique:admin_permissions,code',
                'regex:/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/',
            ],
            'type' => ['required', 'integer', Rule::in(array_keys(AdminPermissionService::TYPES))],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $permission = $this->permService->create($data);

        return $this->success($permission, '创建成功');
    }

    public function show(AdminPermission $permission)
    {
        return $this->success([
            'id' => $permission->id,
            'parent_id' => $permission->parent_id,
            'name' => $permission->name,
            'code' => $permission->code,
            'module' => $permission->module,
            'type' => $permission->type,
            'type_label' => $permission->typeLabel(),
            'sort' => $permission->sort,
            'remark' => $permission->remark,
            'roles' => $permission->roles()->pluck('name')->all(),
            'menu_count' => $permission->menus()->count(),
            'created_at' => $permission->created_at->getTimestamp(),
        ]);
    }

    public function update(Request $request, AdminPermission $permission)
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:admin_permissions,id', Rule::notIn([$permission->id])],
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'code' => [
                'sometimes', 'string', 'max:100',
                'regex:/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/',
                Rule::unique('admin_permissions,code')->ignore($permission->id),
            ],
            'type' => ['sometimes', 'integer', Rule::in(array_keys(AdminPermissionService::TYPES))],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $this->permService->update($permission, $data);

        return $this->success($updated, '更新成功');
    }

    public function destroy(AdminPermission $permission)
    {
        $this->permService->delete($permission);

        return $this->success(['deleted' => true], '删除成功');
    }

    public function batch(Request $request)
    {
        $data = $request->validate([
            'module' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'module_name' => ['required', 'string', 'max:50'],
            'actions' => ['nullable', 'array'],
            'actions.*' => [Rule::in(AdminPermissionService::ACTIONS)],
        ]);

        $this->permService->batchCreate(
            $data['module'],
            $data['module_name'],
            $data['actions'] ?? null,
        );

        return $this->success(['created' => true], '批量创建成功');
    }

    public function codes(Request $request)
    {
        return $this->success($this->permService->codes($request->string('module') ?: null));
    }

    public function mine(Request $request)
    {
        $user = $request->user();

        return $this->success([
            'is_super_admin' => $user->isSuperAdmin(),
            'permissions' => $user->cachedPermissionCodes(),
        ]);
    }
}