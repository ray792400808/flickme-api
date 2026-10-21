<?php

namespace App\Services\Admin;

use App\Models\AdminPermission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminPermissionService
{
    public const TYPES = [
        AdminPermission::TYPE_MODULE => '模块',
        AdminPermission::TYPE_PAGE => '页面',
        AdminPermission::TYPE_BUTTON => '按钮',
    ];

    public const ACTIONS = ['view', 'create', 'edit', 'delete', 'export', 'import', 'audit'];

    // ─── 列表（树） ─────────────────────────────────────────

    public function list(array $filters): array
    {
        $query = AdminPermission::query()->with('parent:id,name,code');

        if (! empty($filters['keyword'])) {
            $keyword = trim($filters['keyword']);
            $query->where(fn ($q) => $q->where('name', 'like', "%{$keyword}%")
                ->orWhere('code', 'like', "%{$keyword}%"));
        }

        if (! empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (isset($filters['type'])) {
            $query->where('type', (int) $filters['type']);
        }

        $permissions = $query->orderBy('module')->orderBy('type')->orderBy('sort')->get();

        return [
            'tree' => $this->buildTree($permissions),
            'flat' => $permissions->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'module' => $p->module,
                'type' => $p->type,
                'type_label' => $p->typeLabel(),
                'sort' => $p->sort,
                'remark' => $p->remark,
            ])->all(),
            'types' => self::TYPES,
            'actions' => self::ACTIONS,
        ];
    }

    // ─── 创建 ───────────────────────────────────────────────

    public function create(array $data): AdminPermission
    {
        $data['module'] = explode('.', $data['code'])[0];

        return AdminPermission::query()->create($data);
    }

    // ─── 更新 ───────────────────────────────────────────────

    public function update(AdminPermission $permission, array $data): AdminPermission
    {
        // 改权限码会让已授权的角色失去该权限
        if (isset($data['code']) && $data['code'] !== $permission->code && $permission->roles()->exists()) {
            abort(422, "权限码 {$permission->code} 已被角色引用，请先解除授权后再修改");
        }

        // 防止挂到自身或后代下形成环
        if (isset($data['parent_id']) && $this->isDescendant($permission, $data['parent_id'])) {
            abort(422, '不能将权限移动到其子级下');
        }

        if (isset($data['code'])) {
            $data['module'] = explode('.', $data['code'])[0];
        }

        $permission->update($data);

        return $permission->fresh();
    }

    // ─── 删除 ───────────────────────────────────────────────

    public function delete(AdminPermission $permission): void
    {
        if ($permission->roles()->exists()) {
            abort(422, '该权限已授权给角色，请先解除授权');
        }

        if ($permission->children()->exists()) {
            abort(422, '请先删除子级权限');
        }

        if ($permission->menus()->exists()) {
            abort(422, '该权限被菜单引用，请先调整菜单');
        }

        DB::transaction(function () use ($permission) {
            $permission->users()->detach();
            $permission->delete();
        });
    }

    // ─── 批量创建模块按钮权限 ────────────────────────────────

    public function batchCreate(string $module, string $moduleName, ?array $actions = null): void
    {
        $actions = $actions ?? self::ACTIONS;
        $labels = [
            'view' => '查看', 'create' => '创建', 'edit' => '编辑', 'delete' => '删除',
            'export' => '导出', 'import' => '导入', 'audit' => '审核',
        ];

        DB::transaction(function () use ($module, $moduleName, $actions, $labels) {
            $parent = AdminPermission::query()->firstOrCreate(
                ['code' => $module],
                [
                    'name' => $moduleName,
                    'module' => $module,
                    'type' => AdminPermission::TYPE_MODULE,
                    'sort' => 0,
                    'remark' => '模块根权限',
                ]
            );

            foreach (array_values($actions) as $i => $action) {
                AdminPermission::query()->firstOrCreate(
                    ['code' => "{$module}.{$action}"],
                    [
                        'parent_id' => $parent->id,
                        'module' => $module,
                        'name' => $labels[$action] ?? $action,
                        'type' => AdminPermission::TYPE_BUTTON,
                        'sort' => ($i + 1) * 10,
                    ]
                );
            }
        });
    }

    // ─── 权限码清单 ────────────────────────────────────────

    public function codes(?string $module = null): Collection
    {
        $query = AdminPermission::query()->orderBy('module')->orderBy('sort');

        if ($module !== null) {
            $query->where('module', $module);
        }

        return $query->pluck('code');
    }

    // ─── 内部 ───────────────────────────────────────────────

    private function buildTree(Collection $permissions): array
    {
        $grouped = $permissions->groupBy(fn ($p) => $p->parent_id ?? 0);
        $build = function (int $parentId) use (&$build, $grouped): array {
            $branch = [];
            foreach ($grouped->get($parentId, collect()) as $p) {
                $node = [
                    'id' => $p->id,
                    'parent_id' => $p->parent_id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'module' => $p->module,
                    'type' => $p->type,
                    'type_label' => $p->typeLabel(),
                    'sort' => $p->sort,
                    'remark' => $p->remark,
                ];
                $children = $build($p->id);
                if ($children !== []) {
                    $node['children'] = $children;
                }
                $branch[] = $node;
            }

            return $branch;
        };

        return $build(0);
    }

    private function isDescendant(AdminPermission $permission, int $candidateParentId): bool
    {
        $cursor = AdminPermission::query()->find($candidateParentId);
        while ($cursor) {
            if ($cursor->id === $permission->id) {
                return true;
            }
            $cursor = $cursor->parent_id ? AdminPermission::query()->find($cursor->parent_id) : null;
        }

        return false;
    }
}
