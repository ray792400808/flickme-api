<?php

namespace App\Models;

use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class AdminUser extends Model
{
    use HasFactory, HasUnixTimestamps, SoftDeletes;

    protected $table = 'admin_users';

    protected $fillable = [
        'name', 'email', 'password', 'avatar', 'status', 'login_ip', 'login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'password' => 'hashed',
            'login_at' => 'integer',
        ];
    }

    // ---------------- 关系 ----------------

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_user', 'admin_user_id', 'admin_role_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AdminPermission::class, 'admin_user_permission', 'admin_user_id', 'admin_permission_id');
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(AdminMenu::class, 'admin_role_menu', 'admin_user_id', 'admin_menu_id');
    }

    public function operationLogs(): HasMany
    {
        return $this->hasMany(AdminOperationLog::class, 'admin_user_id');
    }

    // ---------------- 状态判定 ----------------

    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('admin_roles.slug', 'super_admin')->exists();
    }

    // ---------------- 权限 ----------------

    /**
     * 全部权限码 = 角色权限 ∪ 用户额外授权
     */
    public function permissionCodes(): array
    {
        $fromRoles = AdminPermission::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('admin_roles.id', $this->roles()->select('admin_roles.id')))
            ->pluck('code');

        $fromUser = $this->permissions()->pluck('code');

        return $fromRoles->merge($fromUser)->unique()->values()->all();
    }

    /**
     * 是否拥有某权限（支持 user.create / user.* 通配）
     */
    public function hasPermission(string ...$codes): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        $owned = $this->permissionCodes();
        foreach ($codes as $code) {
            if (in_array($code, $owned, true)) {
                return true;
            }
            $module = explode('.', $code)[0];
            if (in_array("$module.*", $owned, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 可见菜单的权限码集合，用于批量过滤
     */
    public function accessibleMenuIds(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null; // null 表示不受限
        }
        $codes = $this->permissionCodes();

        return AdminMenu::query()
            ->where(function ($q) use ($codes) {
                $q->whereNull('permission_id')
                    ->orWhereIn('permission_id', function ($sub) use ($codes) {
                        $sub->select('id')->from('admin_permissions')->whereIn('code', $codes);
                    });
            })
            ->pluck('id')
            ->all();
    }

    /**
     * 缓存的权限码（同一请求内只算一次）
     */
    public function cachedPermissionCodes(): array
    {
        return once(fn () => $this->permissionCodes());
    }

    // ---------------- 令牌 ----------------

    /**
     * 登录成功写入缓存
     */
    public function issueToken(): array
    {
        $token = bin2hex(random_bytes(32));
        $minutes = (int) config('admin.token_minutes', 120);
        Cache::put($this->tokenKey($token), [
            'id' => $this->id,
            'version' => $this->tokenVersion(),
        ], now()->addMinutes($minutes));

        return ['token' => $token, 'expires_at' => now()->addMinutes($minutes)->toIso8601String()];
    }

    public function revokeToken(string $token): void
    {
        Cache::forget($this->tokenKey($token));
    }

    public function tokenKey(string $token): string
    {
        return 'admin:token:'.hash('sha256', $token);
    }

    /**
     * 会话代次：改密 / 强制下线时自增，令所有已签发令牌立即失效
     */
    public function sessionEpoch(): int
    {
        return (int) Cache::get($this->epochKey(), 1);
    }

    public function bumpSessionEpoch(): void
    {
        Cache::forever($this->epochKey(), $this->sessionEpoch() + 1);
    }

    public function epochKey(): string
    {
        return 'admin:epoch:'.$this->id;
    }

    /**
     * 令牌版本指纹：角色、状态或会话代次变化时，旧令牌全部作废
     */
    public function tokenVersion(): int
    {
        $roles = $this->roles()->pluck('admin_roles.id')->sort()->implode(',');

        return crc32($roles.'|'.(int) $this->status.'|'.$this->sessionEpoch());
    }
}
