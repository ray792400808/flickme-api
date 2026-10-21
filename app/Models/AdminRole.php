<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminRole extends Model
{
    use HasFactory, SoftDeletes, HasUnixTimestamps;

    protected $table = 'admin_roles';

    protected $fillable = [
        'name', 'slug', 'description', 'status', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(AdminUser::class, 'admin_role_user', 'admin_role_id', 'admin_user_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AdminPermission::class, 'admin_role_permission', 'admin_role_id', 'admin_permission_id');
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(AdminMenu::class, 'admin_role_menu', 'admin_role_id', 'admin_menu_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === 'super_admin';
    }

    public function permissionCodes(): array
    {
        return $this->permissions()->pluck('code')->all();
    }
}
