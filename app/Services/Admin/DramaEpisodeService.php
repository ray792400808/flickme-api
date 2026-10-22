<?php

namespace App\Services\Admin;

use App\Models\Drama;
use App\Models\DramaEpisode;
use Illuminate\Support\Facades\DB;

/**
 * 分集（剧集）业务逻辑。
 *
 * 任何写操作结束后都会重新计算 `dramas.episodes_num`，避免列表页分集数不一致。
 */
class DramaEpisodeService
{
    /**
     * 某部剧的分集列表。
     *
     * @return array<int, DramaEpisode>
     */
    public function listFor(Drama $drama): array
    {
        return $drama->episodes()->get()->all();
    }

    /**
     * 新增分集。
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Drama $drama, array $data): DramaEpisode
    {
        return DB::transaction(function () use ($drama, $data): DramaEpisode {
            $this->assertEpisodeNoAvailable($drama, (int) $data['episode_no']);

            $episode = DramaEpisode::create([
                'drama_id' => $drama->id,
                'episode_no' => $data['episode_no'],
                'title' => $data['title'] ?? null,
                'duration_sec' => $data['duration_sec'] ?? 0,
                'status' => $data['status'] ?? 1,
                'is_pay' => $data['is_pay'] ?? 0,
                'video_url' => $data['video_url'] ?? null,
                'poster_url' => $data['poster_url'] ?? null,
            ]);

            $drama->syncEpisodesNum();

            return $episode;
        });
    }

    /**
     * 更新分集（含集号变更），并重新计算剧集总数。
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Drama $drama, DramaEpisode $episode, array $data): DramaEpisode
    {
        return DB::transaction(function () use ($drama, $episode, $data): DramaEpisode {
            if (isset($data['episode_no']) && (int) $data['episode_no'] !== $episode->episode_no) {
                $this->assertEpisodeNoAvailable($drama, (int) $data['episode_no'], $episode->id);
            }

            $episode->fill([
                'episode_no' => $data['episode_no'] ?? $episode->episode_no,
                'title' => array_key_exists('title', $data) ? $data['title'] : $episode->title,
                'duration_sec' => $data['duration_sec'] ?? $episode->duration_sec,
                'status' => $data['status'] ?? $episode->status,
                'is_pay' => $data['is_pay'] ?? $episode->is_pay,
                'video_url' => array_key_exists('video_url', $data) ? $data['video_url'] : $episode->video_url,
                'poster_url' => array_key_exists('poster_url', $data) ? $data['poster_url'] : $episode->poster_url,
            ]);

            $episode->save();

            // 修改分集后必须重新更新冗余字段，避免脏数据
            $drama->syncEpisodesNum();

            return $episode;
        });
    }

    /**
     * 删除分集并回写剧集总数。
     */
    public function delete(Drama $drama, DramaEpisode $episode): void
    {
        DB::transaction(function () use ($drama, $episode): void {
            $episode->delete();

            $drama->syncEpisodesNum();
        });
    }

    /**
     * 集号在单剧内唯一。
     */
    private function assertEpisodeNoAvailable(Drama $drama, int $episodeNo, ?int $ignoreEpisodeId = null): void
    {
        $query = DramaEpisode::where('drama_id', $drama->id)->where('episode_no', $episodeNo);

        if ($ignoreEpisodeId !== null) {
            $query->where('id', '!=', $ignoreEpisodeId);
        }

        if ($query->exists()) {
            abort(422, '该集号已存在');
        }
    }
}
