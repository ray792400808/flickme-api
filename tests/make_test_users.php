<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Contracts\Console\Kernel;

$u = AdminUser::query()->updateOrCreate(
    ['email' => 'ops@test.com'],
    ['name' => 'Ops User', 'password' => 'password123', 'status' => true]
);

$u->roles()->sync([AdminRole::query()->where('slug', 'operator')->firstOrFail()->id]);
$u->permissions()->sync(
    AdminPermission::query()->where('code', 'user.view')->pluck('id')->all()
);

// A second account that only gets log.view via a role (no direct permission)
$role = AdminRole::query()->where('slug', 'finance')->first();
$auditor = AdminUser::query()->updateOrCreate(
    ['email' => 'audit@test.com'],
    ['name' => 'Audit User', 'password' => 'password123', 'status' => true]
);
$auditor->roles()->sync([$role->id]);
$auditor->permissions()->sync([]);

echo "ops@test.com  -> id={$u->id}  perms=[user.view] via direct grant\n";
echo "audit@test.com -> id={$auditor->id}  perms=[log.view] via role finance\n";
