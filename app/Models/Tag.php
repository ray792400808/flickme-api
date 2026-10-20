<?php

namespace App\Models;

use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    use HasFactory, SoftDeletes;
    use HasUnixTimestamps;

    protected $guarded = [];

    public function dramas(): BelongsToMany
    {
        return $this->belongsToMany(Drama::class);
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(StoryCharacter::class);
    }
}
