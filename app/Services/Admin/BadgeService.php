<?php

namespace App\Services\Admin;

use App\Models\Badge;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 角标字典业务逻辑。
 */
class BadgeService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Badge>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Badge::query();

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];

            $query->where(function ($builder) use ($keyword): void {
                $builder->where('keyword', 'like', "%{$keyword}%")
                    ->orWhere('label', 'like', "%{$keyword}%");
            });
        }

        if (isset($filters['enabled'])) {
            $query->where('enabled', $filters['enabled']);
        }

        return $query->orderBy('sort')->orderBy('id')->paginate($perPage);
    }

    /**
     * 已启用的角标（下拉选项用）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function options(): array
    {
        return Badge::enabled()
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'keyword', 'label'])
            ->map(fn (Badge $badge): array => [
                'id' => $badge->id,
                'keyword' => $badge->keyword,
                'label' => $badge->label,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Badge
    {
        return Badge::create([
            'keyword' => $data['keyword'],
            'label' => $data['label'] ?? null,
            'sort' => $data['sort'] ?? 0,
            'enabled' => $data['enabled'] ?? 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Badge $badge, array $data): Badge
    {
        $badge->fill([
            'keyword' => $data['keyword'] ?? $badge->keyword,
            'label' => array_key_exists('label', $data) ? $data['label'] : $badge->label,
            'sort' => $data['sort'] ?? $badge->sort,
            'enabled' => $data['enabled'] ?? $badge->enabled,
        ]);

        $badge->save();

        return $badge;
    }

    /**
     * 删除角标（软删除）。
     *
     * 已挂载该角标的剧集会解除关联。
     */
    public function delete(Badge $badge): void
    {
        $badge->dramas()->update(['badge_id' => null]);
        $badge->delete();
    }
}
