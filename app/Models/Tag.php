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
 * 标签与合集表。
 *
 * `type`：1=标签 / 2=合集；`status`：1=启用 / 2=停用。
 * `language_id` + `language_code` 指向默认语种，多语言内容走 tag_translations。
 */
class Tag extends Model
{
    use HasUnixTimestamps, SoftDeletes;

    public const TYPE_TAG = 1;

    public const TYPE_COLLECTION = 2;

    public const STATUS_ENABLED = 1;

    public const STATUS_DISABLED = 2;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'status' => 'integer',
            'sort' => 'integer',
        ];
    }

    // ── relations ──────────────────────────────────────────

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(TagTranslation::class);
    }

    public function dramas(): BelongsToMany
    {
        return $this->belongsToMany(Drama::class, 'drama_tag');
    }

    // ── scopes ─────────────────────────────────────────────

    /** 已启用的标签 / 合集 */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /** 标签（type=1） */
    public function scopeOnlyTag(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_TAG);
    }

    /** 合集（type=2） */
    public function scopeOnlyCollection(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_COLLECTION);
    }

    // ── accessors ──────────────────────────────────────────

    /** 多语名称：优先取指定语种翻译，缺失时回退默认语种名称 */
    public function getName(string $languageCode): string
    {
        $trans = $this->translations->firstWhere('language_code', $languageCode);

        return $trans?->name ?? $this->name;
    }

    /** 多语描述 */
    public function getDescription(?string $languageCode): ?string
    {
        if (! $languageCode) {
            return $this->description;
        }

        $trans = $this->translations->firstWhere('language_code', $languageCode);

        return $trans?->description ?? $this->description;
    }

    /** 类型中文名 */
    public function getTypeLabelAttribute(): string
    {
        return $this->type === self::TYPE_COLLECTION ? '合集' : '标签';
    }

    /** 状态中文名 */
    public function getStatusLabelAttribute(): string
    {
        return $this->status === self::STATUS_ENABLED ? '已发布' : '已停用';
    }
}
