<?php

namespace App\Services\Admin;

use App\Models\AdminRole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminRoleService
{
    // ─── 列表 ───────────────────────────────────────────────

    public function list(array $filters): LengthAwarePaginator
    {
        $query = AdminRole::query()->withCount(['users', 'permissions']);

        if (! empty($filters['keyword'])) {
            $keyword = trim($filters['keyword']);
            $query->where(fn ($q) => $q->where('name', 'like', "%{$keyword}%")
                ->orWhere('slug', 'like', "%{$keyword}%"));
        }

        if (isset($filters['status'])) {
            $query->where('status', (bool) $filters['status']);
        }

        return $query->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    // ─── 创建 ───────────────────────────────────────────────

    public function create(array $data): AdminRole
    {
        return DB::transaction(function () use ($data) {
            $role = AdminRole::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? true,
            ]);

            if (! empty($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            return $role;
        });
    }

    // ─── 更新 ───────────────────────────────────────────────

    public function update(AdminRole $role, array $data): AdminRole
    {
        return DB::transaction(function () use ($role, $data) {
            // 超级管理员的标识固定，权限恒为全部，不接受外部改写
            if ($role->isSuperAdmin()) {
                unset($data['slug'], $data['permission_ids']);
            }

            $role->update(collect($data)->except('permission_ids')->all());

            if (array_key_exists('permission_ids', $data)) {
                $role->permissions()->sync($data['permission_ids']);
            }

            return $role->fresh();
        });
    }

    // ─── 删除 ───────────────────────────────────────────────

    public function delete(AdminRole $role): void
    {
        if ($role->isSuperAdmin()) {
            abort(422, '超级管理员角色不能删除');
        }

        if ($role->is_default) {
            abort(422, '默认角色不能删除');
        }

        if ($role->users()->exists()) {
            abort(422, '该角色下仍有用户，请先解除关联');
        }

        DB::transaction(function () use ($role) {
            $role->permissions()->detach();
            $role->menus()->detach();
            $role->delete();
        });
    }
}
