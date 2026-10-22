<?php

namespace App\Models;

use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 标签 / 合集的多语言翻译。
 *
 * (tag_id, language_id) 唯一；`language_code` 是 BCP47 缓存字段，便于按语种筛选。
 */
class TagTranslation extends Model
{
    use HasUnixTimestamps, SoftDeletes;

    protected $guarded = [];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
