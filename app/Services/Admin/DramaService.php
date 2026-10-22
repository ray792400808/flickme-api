<?php

namespace App\Services\Admin;

use App\Models\Drama;
use App\Models\DramaEpisode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 剧库（Short 剧）业务逻辑。
 *
 * `dramas.episodes_num` 是分集总数的冗余字段：任何分集的新增 / 修改 / 删除都会
 * 调用 {@see Drama::syncEpisodesNum()} 重新计算，保证列表页不出现脏数据。
 */
class DramaService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Drama>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Drama::query()->with(['badge', 'language', 'tags:id,code,name', 'creator']);

        if (! empty($filters['q'])) {
            $keyword = $filters['q'];

            $query->where(function ($builder) use ($keyword): void {
                $builder->where('id', $keyword)
                    ->orWhere('title', 'like', "%{$keyword}%");
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['badge_id'])) {
            $query->where('badge_id', $filters['badge_id']);
        }

        if (! empty($filters['tag_id'])) {
            $query->whereHas('tags', fn ($builder) => $builder->where('tags.id', $filters['tag_id']));
        }

        $sort = $filters['sort'] ?? 'id';
        $direction = $filters['dir'] ?? 'desc';

        return $query->orderBy($sort, $direction)->orderBy('id', $direction)->paginate($perPage);
    }

    /**
     * 新建剧集。
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $adminId = null): Drama
    {
        return DB::transaction(function () use ($data, $adminId): Drama {
            $status = $data['status'] ?? 0;

            $drama = Drama::create([
                'title' => $data['title'],
                'synopsis' => $data['synopsis'] ?? null,
                'cover_url' => $data['cover_url'] ?? null,
                'video_url' => $data['video_url'] ?? null,
                'status' => $status,
                'badge_id' => $data['badge_id'] ?? null,
                'language_id' => $data['language_id'],
                'free_episodes' => $data['free_episodes'] ?? 6,
                'creator_admin_id' => $adminId,
                'updated_by' => $adminId,
                'published_at' => $status === 1 ? time() : null,
            ]);

            if (! empty($data['tag_ids'])) {
                $drama->tags()->attach($data['tag_ids']);
            }

            $this->insertEpisodes($drama, $data['episodes'] ?? []);

            return $drama;
        });
    }

    /**
     * 更新剧集。
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Drama $drama, array $data, ?int $adminId = null): Drama
    {
        return DB::transaction(function () use ($drama, $data, $adminId): Drama {
            $attributes = [];

            foreach (['title', 'synopsis', 'cover_url', 'video_url', 'badge_id', 'language_id', 'free_episodes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

            // 状态流转：上架时记录上架时间；下架保留最近一次上架时间
            if (array_key_exists('status', $data) && $data['status'] !== $drama->status) {
                $attributes['status'] = $data['status'];

                if ($data['status'] === 1) {
                    $attributes['published_at'] = time();
                }
            }

            if ($adminId !== null) {
                $attributes['updated_by'] = $adminId;
            }

            $drama->fill($attributes);
            $drama->save();

            if (array_key_exists('tag_ids', $data)) {
                $drama->tags()->sync($data['tag_ids'] ?? []);
            }

            return $drama;
        });
    }

    /**
     * 删除剧集（软删除），同时软删除分集并解除标签关联。
     */
    public function delete(Drama $drama): void
    {
        DB::transaction(function () use ($drama): void {
            DramaEpisode::where('drama_id', $drama->id)->get()->each(
                fn (DramaEpisode $episode) => $episode->delete()
            );

            $drama->tags()->detach();
            $drama->delete();
        });
    }

    /**
     * 批量写入分集并同步冗余字段 episodes_num。
     *
     * @param  array<int, array<string, mixed>>  $episodes
     */
    private function insertEpisodes(Drama $drama, array $episodes): void
    {
        if ($episodes === []) {
            $drama->syncEpisodesNum();

            return;
        }

        $now = time();
        $rows = [];

        foreach ($episodes as $episode) {
            $rows[] = [
                'drama_id' => $drama->id,
                'episode_no' => $episode['episode_no'],
                'title' => $episode['title'] ?? null,
                'duration_sec' => $episode['duration_sec'] ?? 0,
                'status' => $episode['status'] ?? 1,
                'is_pay' => $episode['is_pay'] ?? 0,
                'video_url' => $episode['video_url'] ?? null,
                'poster_url' => $episode['poster_url'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DramaEpisode::insert($rows);

        $drama->syncEpisodesNum();
    }
}
