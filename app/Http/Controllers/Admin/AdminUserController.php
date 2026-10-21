<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserService $userService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $users = $this->userService->list($request->all());

        return response()->json(['data' => $users]);
    }

    public function store(Request $request): JsonResponse
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

        return response()->json(['data' => ['created' => true]], 201);
    }

    public function show(AdminUser $user): JsonResponse
    {
        $user->load(['roles:id,name,slug', 'permissions:id,name,code']);

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'status' => $user->status,
            'role_ids' => $user->roles->pluck('id')->all(),
            'permission_ids' => $user->permissions->pluck('id')->all(),
            'roles' => $user->roles,
            'login_at' => $user->login_at?->toIso8601String(),
            'login_ip' => $user->login_ip,
            'created_at' => $user->created_at->toIso8601String(),
        ]]);
    }

    public function update(Request $request, AdminUser $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'email' => ['sometimes', 'required', 'email', 'max:100', Rule::unique('admin_users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:64'],
            'status' => ['sometimes', 'boolean'],
            'role_ids' => ['sometimes', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:admin_roles,id'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:admin_permissions,id'],
        ]);
        $operator = $request->user();

        if ($operator->is($user)) {
            if (isset($data['status']) && ! $data['status']) {
                return response()->json(['message' => '不能停用当前登录的账号'], 422);
            }
            if (isset($data['role_ids']) && ! $this->userService->stillSuperAdminAfter($user, $data['role_ids'])) {
                return response()->json(['message' => '不能移除自己的超级管理员角色'], 422);
            }
        }

        $updated = $this->userService->update($user, $data);

        return response()->json(['data' => $updated]);
    }

    public function destroy(Request $request, AdminUser $user): JsonResponse
    {
        $this->userService->delete($user, $request->user());

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function forceLogout(Request $request, AdminUser $user): JsonResponse
    {
        $this->userService->forceLogout($user, $request->user());

        return response()->json(['data' => ['logged_out' => true]]);
    }

    public function resetPassword(Request $request, AdminUser $user): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:64'],
        ]);

        $this->userService->resetPassword($user, $data['password'], $request->user());

        return response()->json(['data' => ['reset' => true]]);
    }
}
