<?php

namespace App\Models;

use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 剧集（分集）表。
 *
 * `episode_no` 在单剧内唯一；`is_pay` 标记是否需要金币解锁。
 */
class DramaEpisode extends Model
{
    use HasUnixTimestamps, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'episode_no' => 'integer',
            'duration_sec' => 'integer',
            'status' => 'integer',
            'is_pay' => 'integer',
        ];
    }

    public function drama(): BelongsTo
    {
        return $this->belongsTo(Drama::class);
    }

    // ── scopes ─────────────────────────────────────────────

    /** 已上架的分集 */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
