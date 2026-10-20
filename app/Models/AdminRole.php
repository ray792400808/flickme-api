<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminRole extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }
}
