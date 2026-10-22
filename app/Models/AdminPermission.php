<?php

namespace App\Models;

use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminPermission extends Model
{
    use HasFactory, HasUnixTimestamps, SoftDeletes;

    protected $table = 'admin_permissions';

    /** 模块 / 页面 / 按钮 */
    public const TYPE_MODULE = 1;

    public const TYPE_PAGE = 2;

    public const TYPE_BUTTON = 3;

    protected $fillable = [
        'parent_id', 'module', 'name', 'code', 'type', 'sort', 'remark',
    ];

    protected function casts(): array
    {
        return ['type' => 'integer', 'sort' => 'integer'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_permission', 'admin_permission_id', 'admin_role_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(AdminUser::class, 'admin_user_permission', 'admin_permission_id', 'admin_user_id');
    }

    public function menus(): HasMany
    {
        return $this->hasMany(AdminMenu::class, 'permission_id');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_MODULE => '模块',
            self::TYPE_PAGE => '页面',
            self::TYPE_BUTTON => '按钮',
            default => '未知',
        };
    }
}
