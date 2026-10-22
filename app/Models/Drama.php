<?php

namespace App\Models;

use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 剧库主表。
 *
 * `episodes_num` 是分集数量的冗余字段：任何分集的新增 / 修改 / 删除都必须调用
 * {@see self::syncEpisodesNum()} 重新计算，避免列表页出现脏数据。
 */
class Drama extends Model
{
    use HasUnixTimestamps, SoftDeletes;

    protected $guarded = [];

    // status：0 草稿 / 1 上架 / 2 下架
    public const STATUS_DRAFT = 0;

    public const STATUS_PUBLISHED = 1;

    public const STATUS_OFFLINE = 2;

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'free_episodes' => 'integer',
            'episodes_num' => 'integer',
            'published_at' => 'integer',
            'created_at' => 'integer',
            'updated_at' => 'integer',
        ];
    }

    // ── relations ──────────────────────────────────────────

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'creator_admin_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'drama_tag');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(DramaEpisode::class)->orderBy('episode_no');
    }

    // ── scopes ─────────────────────────────────────────────

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * 可被运营位引用的剧集：已上架即可。
     *
     * 旧结构有 is_playable 与 video_url 两个字段，重建表后已移除，
     * 因此「可用」现在等价于 status = 1（上架）。
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    // ── 业务方法 ───────────────────────────────────────────

    /** 已发布且有可播放视频的集数 */
    public function getPlayableCount(): int
    {
        return $this->episodes()
            ->where('status', 1)
            ->whereNotNull('video_url')
            ->where('video_url', '!=', '')
            ->count();
    }

    /**
     * 热度排序权重（运营位热榜用）。
     *
     * 旧结构有 heat 列，最终表结构里没有；这里改为按「上架时间 + 集数」派生，
     * 让热榜排序仍然可用，同时避免为了排序再加冗余字段。
     */
    public function getHeatAttribute(): int
    {
        $recency = $this->published_at ?? $this->created_at ?? $this->updated_at ?? 0;

        return (int) $recency + $this->episodes_num * 100;
    }

    /**
     * 重新计算并写入冗余字段 `episodes_num`（分集总数）。
     *
     * 分集新增、修改（含集号变更）、删除后都必须调用。
     */
    public function syncEpisodesNum(): int
    {
        $count = $this->episodes()->count();

        $this->updateQuietly(['episodes_num' => $count]);

        return $count;
    }
}
