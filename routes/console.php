<?php

use App\Models\AdminAccount;
use App\Models\AdminRole;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:bootstrap {--email= : 初始管理员邮箱}', function (): int {
    if (AdminAccount::query()->exists()) {
        $this->info('已有管理员，不重复导入');

        return 0;
    }
    $email = $this->option('email') ?: config('admin.email');
    $hash = config('admin.password_hash');
    if ((! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) && $this->input->isInteractive()) {
        $email = $this->ask('初始超级管理员邮箱');
    }
    if ((! is_string($hash) || (password_get_info($hash)['algo'] ?? null) === null) && $this->input->isInteractive()) {
        $password = $this->secret('设置初始密码（至少 12 个字符，输入不可见）');
        if (! is_string($password) || strlen($password) < 12 || strlen($password) > 128) {
            $this->error('密码长度必须为 12–128 个字符');

            return 1;
        }
        $hash = Hash::make($password);
    }
    if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($hash) || (password_get_info($hash)['algo'] ?? null) === null) {
        $this->error('请先配置 ADMIN_EMAIL 和有效的 ADMIN_PASSWORD_HASH');

        return 1;
    }
    if (AdminAccount::query()->exists()) {
        $this->info('已有管理员，不重复导入');

        return 0;
    }
    DB::transaction(function () use ($email, $hash): void {
        AdminRole::query()->where('key', 'admin')->lockForUpdate()->firstOrFail();
        if (! AdminAccount::query()->exists()) {
            AdminAccount::query()->create(['name' => '超级管理员', 'email' => strtolower($email), 'password' => $hash, 'role_key' => 'admin', 'status' => 'active']);
        }
    });
    $this->info('初始超级管理员已导入，后续账号请在后台管理');

    return 0;
})->purpose('将现有环境配置导入为初始超级管理员');
