<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\ContentListRequest;
use App\Http\Requests\Admin\Content\DramaEpisodeRequest;
use App\Http\Requests\Admin\Content\DramaRequest;
use App\Models\Drama;
use App\Models\DramaEpisode;
use App\Models\Tag;
use App\Services\Admin\DramaEpisodeService;
use App\Services\Admin\DramaService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Short 剧库接口（含分集管理）。
 */
class DramaController extends Controller
{
    public function __construct(
        private readonly DramaService $dramaService,
        private readonly DramaEpisodeService $dramaEpisodeService,
    ) {}

    public function index(ContentListRequest $request): Response
    {
        $filters = $request->validated();

        return $this->success(
            $this->dramaService->paginate($filters, $filters['per_page'] ?? 10)
                ->through(fn (Drama $drama): array => $this->transform($drama))
        );
    }

    public function store(DramaRequest $request): Response
    {
        $drama = $this->dramaService->create(
            $request->normalized(),
            $request->user()?->id,
        );

        return $this->success($this->transform($drama->fresh()->load('badge', 'language', 'tags', 'creator')));
    }

    public function show(int $drama): Response
    {
        $model = Drama::with(['badge', 'language', 'tags', 'creator', 'episodes'])->findOrFail($drama);

        return $this->success($this->transform($model, true));
    }

    public function update(DramaRequest $request, int $drama): Response
    {
        $model = Drama::with('tags')->findOrFail($drama);

        $this->dramaService->update($model, $request->normalized(), $request->user()?->id);

        return $this->success(
            $this->transform($model->fresh()->load('badge', 'language', 'tags', 'creator', 'episodes'), true)
        );
    }

    public function destroy(int $drama): Response
    {
        $this->dramaService->delete(Drama::findOrFail($drama));

        return $this->success(true);
    }

    // ══════════════════════════════════════════════════════
    // 分集管理
    // ══════════════════════════════════════════════════════

    public function episodes(int $drama): Response
    {
        $model = Drama::findOrFail($drama);

        return $this->success(
            array_map(
                fn (DramaEpisode $episode): array => $this->transformEpisode($episode),
                $this->dramaEpisodeService->listFor($model)
            )
        );
    }

    public function storeEpisode(DramaEpisodeRequest $request, int $drama): Response
    {
        $model = Drama::findOrFail($drama);

        $episode = $this->dramaEpisodeService->create($model, $request->normalized());

        return $this->success($this->transformEpisode($episode->fresh()));
    }

    public function updateEpisode(DramaEpisodeRequest $request, int $drama, int $episode): Response
    {
        $model = Drama::findOrFail($drama);
        $target = DramaEpisode::where('drama_id', $model->id)->findOrFail($episode);

        $updated = $this->dramaEpisodeService->update($model, $target, $request->normalized());

        return $this->success($this->transformEpisode($updated->fresh()));
    }

    public function destroyEpisode(int $drama, int $episode): Response
    {
        $model = Drama::findOrFail($drama);
        $target = DramaEpisode::where('drama_id', $model->id)->findOrFail($episode);

        $this->dramaEpisodeService->delete($model, $target);

        return $this->success(true);
    }

    // ══════════════════════════════════════════════════════
    // helpers
    // ══════════════════════════════════════════════════════

    /**
     * @return array<string, mixed>
     */
    private function transform(Drama $drama, bool $withEpisodes = false): array
    {
        $result = [
            'id' => $drama->id,
            'title' => $drama->title,
            'synopsis' => $drama->synopsis,
            'cover_url' => $drama->cover_url,
            'video_url' => $drama->video_url,
            'status' => $drama->status,
            'status_label' => $this->statusLabel($drama->status),
            'badge_id' => $drama->badge_id,
            'badge' => $drama->badge ? [
                'id' => $drama->badge->id,
                'keyword' => $drama->badge->keyword,
                'label' => $drama->badge->label,
            ] : null,
            'language_id' => $drama->language_id,
            'language' => $drama->language ? [
                'id' => $drama->language->id,
                'code' => $drama->language->code,
                'name' => $drama->language->name,
            ] : null,
            'free_episodes' => $drama->free_episodes,
            'episodes_num' => $drama->episodes_num,
            'creator_admin_id' => $drama->creator_admin_id,
            'creator' => $drama->creator ? [
                'id' => $drama->creator->id,
                'name' => $drama->creator->name,
            ] : null,
            'updated_by' => $drama->updated_by,
            'published_at' => $drama->published_at,
            'created_at' => $drama->created_at,
            'updated_at' => $drama->updated_at,
            'tags' => $drama->tags->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'code' => $tag->code,
                'name' => $tag->name,
            ])->all(),
        ];

        if ($withEpisodes) {
            $result['episodes'] = $drama->episodes
                ->map(fn (DramaEpisode $episode): array => $this->transformEpisode($episode))
                ->all();
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformEpisode(DramaEpisode $episode): array
    {
        return [
            'id' => $episode->id,
            'drama_id' => $episode->drama_id,
            'episode_no' => $episode->episode_no,
            'title' => $episode->title,
            'duration_sec' => $episode->duration_sec,
            'status' => $episode->status,
            'status_label' => $this->statusLabel($episode->status),
            'is_pay' => $episode->is_pay,
            'video_url' => $episode->video_url,
            'poster_url' => $episode->poster_url,
            'created_at' => $episode->created_at,
            'updated_at' => $episode->updated_at,
        ];
    }

    private function statusLabel(int $status): string
    {
        return match ($status) {
            0 => '草稿',
            1 => '上架',
            2 => '下架',
            default => '未知',
        };
    }
}
