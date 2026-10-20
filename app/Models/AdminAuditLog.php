<?php

namespace App\Models;

use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    use HasUnixTimestamps;

    public $timestamps = false;

    protected $guarded = [];
}
