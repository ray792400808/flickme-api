<?php

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * 创建初始超级管理员，幂等。
 * 权限与角色数据由 AdminSeeder 负责，这里只建用户并挂上 super_admin 角色。
 */
Artisan::command('admin:bootstrap {--email= : 初始管理员邮箱} {--name=超级管理员 : 管理员姓名}', function (): int {
    if (AdminUser::query()->where('slug', 'super_admin')->doesntExist() && AdminUser::query()->exists()) {
        // 库中已有用户但没有超管，交由人工处理
    }

    $role = AdminRole::query()->where('slug', 'super_admin')->first();

    if (! $role) {
        $this->error('缺少 super_admin 角色，请先执行: php artisan db:seed --class=AdminSeeder');

        return 1;
    }

    $email = strtolower((string) ($this->option('email') ?: config('admin.email')));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = (string) $this->ask('请输入初始超级管理员邮箱');
    }

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('邮箱格式不正确');

        return 1;
    }

    if (AdminUser::query()->where('email', $email)->exists()) {
        $this->info("管理员 {$email} 已存在，跳过");

        return 0;
    }

    $hash = config('admin.password_hash');
    if (! is_string($hash) || (password_get_info($hash)['algo'] ?? null) === null) {
        $password = (string) $this->secret('设置初始密码（至少 12 位，输入不可见）');
        if (strlen($password) < 12) {
            $this->error('密码长度至少 12 位');

            return 1;
        }
        $hash = Hash::make($password);
    }

    $admin = AdminUser::query()->create([
        'name' => $this->option('name'),
        'email' => $email,
        'password' => $hash,
        'status' => true,
    ]);
    $admin->roles()->attach($role->id);

    $this->info("超级管理员 {$email} 已创建");

    return 0;
})->purpose('创建初始超级管理员账号（需先执行 AdminSeeder）');

/**
 * 校验权限码与路由声明是否一致，防止新增接口时忘记加权限中间件。
 */
Artisan::command('admin:check-permissions', function (): int {
    $declared = [];

    foreach (glob(base_path('routes/admin.php')) as $file) {
        preg_match_all("/CheckAdminPermission::class\s*\.\s*':([^']+)'/", file_get_contents($file), $matches);
        foreach ($matches[1] as $group) {
            foreach (explode(',', $group) as $code) {
                $declared[trim($code)] = true;
            }
        }
    }

    $registered = AdminPermission::query()->pluck('code')->flip();
    $orphanRoutes = array_diff(array_keys($declared), $registered->keys()->all());
    $unusedPermissions = array_diff($registered->keys()->all(), array_keys($declared));

    if ($orphanRoutes !== []) {
        $this->warn('路由使用了未注册的权限码:');
        foreach ($orphanRoutes as $code) {
            $this->line("  - {$code}");
        }
    }

    if ($unusedPermissions !== []) {
        $this->info('以下权限码暂未用于路由（可能仅供前端按钮控制）:');
        foreach ($unusedPermissions as $code) {
            $this->line("  - {$code}");
        }
    }

    return $orphanRoutes === [] ? 0 : 1;
})->purpose('检查路由权限码与权限表是否一致');
