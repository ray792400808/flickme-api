<?php

namespace App\Models;

use App\Models\Concerns\HasUnixTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminMenu extends Model
{
    use HasFactory, HasUnixTimestamps, SoftDeletes;

    protected $table = 'admin_menus';

    protected $fillable = [
        'parent_id', 'permission_id', 'sort', 'title', 'icon', 'path',
        'name', 'component', 'redirect', 'is_hidden', 'is_affix', 'is_keep_alive', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'is_affix' => 'boolean',
            'is_keep_alive' => 'boolean',
            'meta' => 'array',
            'sort' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(AdminPermission::class, 'permission_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_menu', 'admin_menu_id', 'admin_role_id');
    }

    /**
     * 递归生成前端路由树
     */
    public function toRouteTree(): array
    {
        $node = [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'path' => $this->path,
            'name' => $this->name,
            'component' => $this->component,
            'redirect' => $this->redirect,
            'sort' => $this->sort,
            'meta' => array_merge([
                'title' => $this->title,
                'icon' => $this->icon,
                'isAffix' => $this->is_affix,
                'isKeepAlive' => $this->is_keep_alive,
                'isHidden' => $this->is_hidden,
            ], $this->meta ?? []),
        ];

        $children = $this->children()->get()->map(fn ($child) => $child->toRouteTree())->all();
        if ($children !== []) {
            $node['children'] = $children;
        }

        return $node;
    }
}
