<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminAccount extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['password', 'activation_hash', 'token_version'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'activation_expires_at' => 'datetime', 'last_login_at' => 'datetime', 'token_version' => 'integer'];
    }

    public function profile(): array
    {
        $role = AdminRole::query()->where('key', $this->role_key)->firstOrFail();

        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'role' => $role->key, 'roleName' => $role->name, 'permissions' => $role->permissions];
    }
}
