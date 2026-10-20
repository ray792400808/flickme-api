<?php

namespace App\Models;

use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Drama extends Model
{
    use HasFactory, SoftDeletes;
    use HasUnixTimestamps;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_playable' => 'boolean', 'heat' => 'integer'];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('is_playable', true)->whereNotNull('video_url')->where('video_url', '!=', '');
    }
}
