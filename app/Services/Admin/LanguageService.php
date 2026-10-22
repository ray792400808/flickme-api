<?php

namespace App\Services\Admin;

use App\Models\Language;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 语言字典业务逻辑。
 *
 * 语言是标签 / 合集 / 剧库多语言内容的维度。最终表结构没有"系统默认语种"列，
 * 默认语种由 seeder 写入的基础语种与 `sort` 顺序决定。
 */
class LanguageService
{
    /**
     * 分页查询语言列表。
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Language>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Language::query();

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];

            $query->where(function ($builder) use ($keyword): void {
                $builder->where('code', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('native_name', 'like', "%{$keyword}%");
            });
        }

        if (isset($filters['enabled'])) {
            $query->where('enabled', $filters['enabled']);
        }

        return $query->orderBy('sort')->orderBy('id')->paginate($perPage);
    }

    /**
     * 已启用的语言（下拉选项用）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function options(): array
    {
        return Language::enabled()
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'native_name', 'flag_emoji'])
            ->map(fn (Language $language): array => [
                'id' => $language->id,
                'code' => $language->code,
                'name' => $language->name,
                'native_name' => $language->native_name,
                'flag_emoji' => $language->flag_emoji,
            ])
            ->all();
    }

    /**
     * 新建语言。
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Language
    {
        return Language::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'native_name' => $data['native_name'],
            'flag_emoji' => $data['flag_emoji'] ?? null,
            'enabled' => $data['enabled'] ?? 1,
            'sort' => $data['sort'] ?? 0,
        ]);
    }

    /**
     * 更新语言。
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Language $language, array $data): Language
    {
        $language->fill([
            'code' => $data['code'] ?? $language->code,
            'name' => $data['name'] ?? $language->name,
            'native_name' => $data['native_name'] ?? $language->native_name,
            'flag_emoji' => array_key_exists('flag_emoji', $data) ? $data['flag_emoji'] : $language->flag_emoji,
            'enabled' => $data['enabled'] ?? $language->enabled,
            'sort' => $data['sort'] ?? $language->sort,
        ]);

        $language->save();

        return $language;
    }

    /**
     * 删除语言（软删除）。
     *
     * 被标签 / 合集 / 剧库引用时不允许删除，避免留下悬空引用。
     */
    public function delete(Language $language): void
    {
        if ($language->tags()->exists() || $language->dramas()->exists() || $language->tagTranslations()->exists()) {
            abort(422, '该语种已被标签、合集或剧库引用，无法删除，请改为停用');
        }

        $language->delete();
    }
}
