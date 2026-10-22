<?php

namespace App\Services\Admin;

use App\Models\Language;
use App\Models\Tag;
use App\Models\TagTranslation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 标签与合集业务逻辑。
 *
 * type：1=标签 / 2=合集；status：1=启用 / 2=停用。
 * 多语言名称与描述存放在 tag_translations，提交时按"全量替换"语义处理。
 */
class TagService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Tag>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Tag::query()->with(['language', 'translations']);

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];

            $query->where(function ($builder) use ($keyword): void {
                $builder->where('code', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%");
            });
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('type')->orderBy('id')->paginate($perPage);
    }

    /**
     * 下拉选项（默认语种名称，可按语种切换）。
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function options(array $filters = []): array
    {
        $query = Tag::query()->with('translations')->enabled();

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $languageCode = $filters['lang'] ?? 'en-US';

        return $query->orderBy('type')->orderBy('id')->get()
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'code' => $tag->code,
                'name' => $tag->getName($languageCode),
            ])
            ->all();
    }

    /**
     * 每个标签关联的剧数。
     *
     * @return array<int|string, int>
     */
    public function stats(): array
    {
        return DB::table('drama_tag')
            ->select('tag_id', DB::raw('COUNT(*) as drama_count'))
            ->groupBy('tag_id')
            ->pluck('drama_count', 'tag_id')
            ->all();
    }

    /**
     * 新建标签 / 合集。
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $creatorAdminId = null): Tag
    {
        return DB::transaction(function () use ($data, $creatorAdminId): Tag {
            $tag = Tag::create([
                'code' => $data['code'],
                'type' => $data['type'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'language_id' => $data['language_id'],
                'language_code' => $this->languageCode($data['language_id']),
                'status' => $data['status'] ?? Tag::STATUS_ENABLED,
                'creator_admin_id' => $creatorAdminId,
            ]);

            $this->syncTranslations($tag, $data['translations'] ?? []);

            return $tag;
        });
    }

    /**
     * 更新标签 / 合集。
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Tag $tag, array $data): Tag
    {
        return DB::transaction(function () use ($tag, $data): Tag {
            $tag->fill([
                'code' => $data['code'] ?? $tag->code,
                'type' => $data['type'] ?? $tag->type,
                'name' => $data['name'] ?? $tag->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $tag->description,
                'language_id' => $data['language_id'] ?? $tag->language_id,
                'language_code' => isset($data['language_id'])
                    ? $this->languageCode($data['language_id'])
                    : $tag->language_code,
                'status' => $data['status'] ?? $tag->status,
            ]);

            $tag->save();

            // 翻译全量替换：前端提交即视为该语种的完整定义
            if (array_key_exists('translations', $data)) {
                $this->syncTranslations($tag, $data['translations'] ?? []);
            }

            return $tag;
        });
    }

    /**
     * 删除标签 / 合集（软删除），并解除与剧库的关联。
     */
    public function delete(Tag $tag): void
    {
        DB::transaction(function () use ($tag): void {
            $tag->dramas()->detach();
            $tag->delete();
        });
    }

    /**
     * 全量替换某个标签的多语言翻译。
     *
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function syncTranslations(Tag $tag, array $translations): void
    {
        $tag->translations()->delete();

        if ($translations === []) {
            return;
        }

        $languageIds = array_column($translations, 'language_id');
        $languages = Language::whereIn('id', $languageIds)->pluck('code', 'id');

        $now = time();
        $rows = [];

        foreach ($translations as $translation) {
            $rows[] = [
                'tag_id' => $tag->id,
                'language_id' => $translation['language_id'],
                'name' => $translation['name'],
                'description' => $translation['description'] ?? null,
                'language_code' => $languages[$translation['language_id']] ?? '',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        TagTranslation::insert($rows);
    }

    /**
     * 语种 ID → BCP47 code（缓存字段）。
     */
    private function languageCode(int $languageId): string
    {
        return Language::where('id', $languageId)->value('code') ?? '';
    }
}
