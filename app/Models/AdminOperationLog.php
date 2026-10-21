<?php

namespace App\Models;

use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminOperationLog extends Model
{
    use HasFactory, SoftDeletes, HasUnixTimestamps;

    protected $table = 'admin_operation_logs';

    protected $fillable = [
        'admin_user_id', 'username', 'method', 'path', 'ip', 'input', 'description',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'trace_id' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }
}