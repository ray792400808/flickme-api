<?php

namespace App\Models;

use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 语言字典。
 *
 * 语言是标签 / 合集 / 剧库多语言内容的维度，最终表结构中没有"系统默认语种"列，
 * 默认语种由 seeder 写入的基础语种与 `sort` 顺序决定。
 */
class Language extends Model
{
    use HasUnixTimestamps, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'integer',
            'sort' => 'integer',
        ];
    }

    // ── relations ──────────────────────────────────────────

    public function tagTranslations(): HasMany
    {
        return $this->hasMany(TagTranslation::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function dramas(): HasMany
    {
        return $this->hasMany(Drama::class);
    }

    // ── scopes ─────────────────────────────────────────────

    /** 已启用的语种 */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', 1);
    }
}
