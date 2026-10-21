<?php

namespace App\Services\Admin;

use App\Models\AdminOperationLog;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminUserService
{
    // ─── 列表 ───────────────────────────────────────────────

    public function list(array $filters): LengthAwarePaginator
    {
        $query = AdminUser::query()->with('roles:id,name,slug');

        if (! empty($filters['keyword'])) {
            $keyword = trim($filters['keyword']);
            $query->where(fn ($q) => $q->where('name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%"));
        }

        if (! empty($filters['role_id'])) {
            $query->whereHas('roles', fn ($q) => $q->where('admin_roles.id', (int) $filters['role_id']));
        }

        if (isset($filters['status'])) {
            $query->where('status', (bool) $filters['status']);
        }

        return $query->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    // ─── 创建 ───────────────────────────────────────────────

    public function create(array $data): AdminUser
    {
        return DB::transaction(function () use ($data) {
            $user = AdminUser::query()->create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'status' => $data['status'] ?? true,
            ]);

            $user->roles()->sync($data['role_ids']);
            if (! empty($data['permission_ids'])) {
                $user->permissions()->sync($data['permission_ids']);
            }

            return $user;
        });
    }

    // ─── 更新 ───────────────────────────────────────────────

    public function update(AdminUser $user, array $data): AdminUser
    {
        return DB::transaction(function () use ($user, $data) {
            $updates = collect($data)->only(['name', 'email', 'status'])->all();
            if (! empty($data['password'])) {
                $updates['password'] = $data['password'];
            }
            if ($updates !== []) {
                $user->forceFill($updates)->save();
            }

            if (isset($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }
            if (array_key_exists('permission_ids', $data)) {
                $user->permissions()->sync($data['permission_ids'] ?? []);
            }

            if (! empty($data['password'])) {
                $user->bumpSessionEpoch();
            }

            return $user->fresh(['roles:id,name,slug']);
        });
    }

    // ─── 删除 ───────────────────────────────────────────────

    public function delete(AdminUser $user, AdminUser $operator): void
    {
        if ($operator->is($user)) {
            abort(422, '不能删除当前登录的账号');
        }

        if ($user->isSuperAdmin() && $this->activeSuperAdminCount() <= 1) {
            abort(422, '系统至少需要保留一个启用的超级管理员');
        }

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            $user->permissions()->detach();
            $user->delete();
        });

        $user->bumpSessionEpoch();
    }

    // ─── 重置密码 ──────────────────────────────────────────

    public function resetPassword(AdminUser $user, string $password, AdminUser $operator): void
    {
        if ($operator->is($user)) {
            abort(422, '请通过「修改密码」更新自己的密码');
        }

        $user->forceFill(['password' => $password])->save();
        $user->bumpSessionEpoch();
    }

    // ─── 强制下线 ───────────────────────────────────────────

    public function forceLogout(AdminUser $user, AdminUser $operator): void
    {
        if ($operator->is($user)) {
            abort(422, '不能强制自己下线');
        }

        $user->bumpSessionEpoch();
    }

    // ─── 辅助 ───────────────────────────────────────────────

    public function activeSuperAdminCount(): int
    {
        return AdminUser::query()
            ->where('status', true)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))
            ->count();
    }

    public function stillSuperAdminAfter(AdminUser $user, array $roleIds): bool
    {
        return AdminRole::query()
            ->whereIn('id', $roleIds)
            ->where('slug', 'super_admin')
            ->exists();
    }
}
