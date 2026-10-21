<?php

namespace App\Services\Admin;

use App\Models\AdminMenu;
use Illuminate\Support\Facades\DB;

class AdminMenuService
{
    // ─── 列表（树） ─────────────────────────────────────────

    public function list(?string $keyword = null): array
    {
        $query = AdminMenu::query()->with('permission:id,name,code,type');

        if ($keyword !== null) {
            $keyword = trim($keyword);
            $query->where(fn ($q) => $q->where('title', 'like', "%{$keyword}%")
                ->orWhere('path', 'like', "%{$keyword}%"));
        }

        $menus = $query->orderBy('sort')->orderBy('id')->get();

        return $this->buildTree($menus->groupBy(fn ($m) => $m->parent_id ?? 0)->all(), 0);
    }

    // ─── 下拉选项（扁平） ─────────────────────────────────

    public function options(): array
    {
        return AdminMenu::query()
            ->orderBy('sort')
            ->get(['id', 'parent_id', 'title', 'path', 'sort'])
            ->map(fn ($m) => [
                'id' => $m->id,
                'parent_id' => $m->parent_id,
                'title' => $m->title,
                'path' => $m->path,
                'sort' => $m->sort,
            ])->all();
    }

    // ─── 创建 ───────────────────────────────────────────────

    public function create(array $data): AdminMenu
    {
        return DB::transaction(function () use ($data) {
            return AdminMenu::query()->create($data);
        });
    }

    // ─── 更新 ───────────────────────────────────────────────

    public function update(AdminMenu $menu, array $data): AdminMenu
    {
        if (isset($data['parent_id']) && $this->isDescendant($menu, $data['parent_id'])) {
            abort(422, '不能将菜单移动到其子级下');
        }

        $menu->update($data);

        return $menu->fresh('permission:id,name,code');
    }

    // ─── 删除 ───────────────────────────────────────────────

    public function delete(AdminMenu $menu): void
    {
        if ($menu->children()->exists()) {
            abort(422, '请先删除子菜单');
        }

        DB::transaction(function () use ($menu) {
            $menu->roles()->detach();
            $menu->delete();
        });
    }

    // ─── 拖拽排序 ─────────────────────────────────────────

    public function sort(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $target = AdminMenu::query()->findOrFail($item['id']);

                if (isset($item['parent_id']) && $this->isDescendant($target, $item['parent_id'])) {
                    throw new \RuntimeException("菜单「{$target->title}」不能移动到其子级下");
                }

                $target->update([
                    'parent_id' => $item['parent_id'] ?? null,
                    'sort' => $item['sort'],
                ]);
            }
        });
    }

    // ─── 内部 ───────────────────────────────────────────────

    private function buildTree(array $grouped, int $parentId): array
    {
        $branch = [];
        foreach ($grouped[$parentId] ?? collect() as $menu) {
            $node = [
                'id' => $menu->id,
                'parent_id' => $menu->parent_id,
                'permission_id' => $menu->permission_id,
                'permission_code' => $menu->permission?->code,
                'title' => $menu->title,
                'icon' => $menu->icon,
                'path' => $menu->path,
                'name' => $menu->name,
                'component' => $menu->component,
                'redirect' => $menu->redirect,
                'sort' => $menu->sort,
                'is_hidden' => $menu->is_hidden,
                'is_affix' => $menu->is_affix,
                'is_keep_alive' => $menu->is_keep_alive,
                'meta' => $menu->meta,
                'children' => $this->buildTree($grouped, $menu->id),
            ];
            $branch[] = $node;
        }

        return $branch;
    }

    private function isDescendant(AdminMenu $menu, ?int $candidateParentId): bool
    {
        if ($candidateParentId === null) {
            return false;
        }

        $cursor = AdminMenu::query()->find($candidateParentId);
        while ($cursor) {
            if ($cursor->id === $menu->id) {
                return true;
            }
            $cursor = $cursor->parent_id ? AdminMenu::query()->find($cursor->parent_id) : null;
        }

        return false;
    }
}
