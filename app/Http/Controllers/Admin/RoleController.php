<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ['groups' => config('admin_permissions.groups'), 'roles' => AdminRole::query()->orderBy('id')->get()]]);
    }

    public function update(Request $request, AdminRole $role): JsonResponse
    {
        abort_unless($request->user()->role_key === 'admin', 403, '仅超级管理员可以修改角色权限');
        abort_if($role->key === 'admin', 422, '超级管理员权限固定为全部读写');
        $groups = array_keys(config('admin_permissions.groups'));
        $data = $request->validate(['permissions' => ['required', 'array:'.implode(',', $groups)], 'permissions.*' => ['required', Rule::in(['none', 'r', 'rw'])]]);
        DB::transaction(function () use ($request, $role, $data): void {
            $role->update(['permissions' => array_filter($data['permissions'], fn (string $value): bool => $value !== 'none')]);
            AdminAuditLog::query()->create(['actor_id' => $request->user()->id, 'actor' => $request->user()->email, 'action' => '修改角色权限', 'subject' => $role->key.' · '.$role->name]);
        });

        return response()->json(['data' => $role->fresh()]);
    }
}
