<?php

namespace App\Models;

use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 角标字典（HOT / NEW / EXCLUSIVE …）。
 *
 * 关键词是稳定标识，创建后不建议修改；剧库通过 dramas.badge_id 单选引用。
 */
class Badge extends Model
{
    use HasUnixTimestamps, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'enabled' => 'integer',
        ];
    }

    // ── relations ──────────────────────────────────────────

    public function dramas(): HasMany
    {
        return $this->hasMany(Drama::class);
    }

    // ── scopes ─────────────────────────────────────────────

    /** 已启用的角标 */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', 1);
    }
}
