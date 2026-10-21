<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

trait TreeBuilder
{
    /**
     * 将扁平数据转换为树形结构
     *
     * @param  \Illuminate\Support\Collection|array  $data
     * @param  int|null  $parentId
     * @return \Illuminate\Support\Collection
     */
    public static function buildTree($data, int $parentId = null): SupportCollection
    {
        $tree = collect();

        foreach ($data as $item) {
            if ($item['parent_id'] === $parentId) {
                $children = self::buildTree($data, $item['id']);
                
                $node = collect($item);
                
                if ($children->isNotEmpty()) {
                    $node->put('children', $children);
                }
                
                $tree->push($node);
            }
        }

        return $tree->sortBy('order')->values();
    }

    /**
     * 获取树形结构（从数据库）
     *
     * @param  int|null  $parentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function tree(int $parentId = null): Collection
    {
        $data = self::orderBy('order')
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'parent_id' => $item->parent_id,
                'order' => $item->order,
                ...$item->toArray(),
            ])
            ->toArray();

        return self::buildTree($data, $parentId);
    }

    /**
     * 获取所有后代ID
     *
     * @return array
     */
    public function getDescendantIds(): array
    {
        $ids = [];
        $children = self::where('parent_id', $this->id)->get();

        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantIds());
        }

        return $ids;
    }

    /**
     * 获取所有祖先ID
     *
     * @return array
     */
    public function getAncestorIds(): array
    {
        $ids = [];
        $parent = $this->parent;

        while ($parent) {
            $ids[] = $parent->id;
            $parent = $parent->parent;
        }

        return $ids;
    }

    /**
     * 获取完整路径名称
     *
     * @param  string  $separator
     * @return string
     */
    public function getFullPath(string $separator = ' / '): string
    {
        $path = [$this->title];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->title);
            $parent = $parent->parent;
        }

        return implode($separator, $path);
    }
}
