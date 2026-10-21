<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json(['data' => [
            'status' => 'ok',
            'service' => 'flickme-admin-api',
            'time' => now()->toIso8601String(),
        ]]);
    }

    public function info(): JsonResponse
    {
        return response()->json(['data' => [
            'application' => config('app.name'),
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => Application::VERSION,
            'timezone' => config('app.timezone'),
            'token_minutes' => (int) config('admin.token_minutes', 120),
            'server_time' => now()->toIso8601String(),
        ]]);
    }

    /**
     * 当前登录者的权限总览，供前端一次性初始化
     */
    public function session(Request $request): JsonResponse
    {
        $user = $request->user();
        $permissionCodes = $user->cachedPermissionCodes();

        return response()->json(['data' => [
            'is_super_admin' => $user->isSuperAdmin(),
            'permissions' => $permissionCodes,
            'role_slugs' => $user->roles()->pluck('slug')->all(),
            'token_expires_in' => (int) config('admin.token_minutes', 120) * 60,
        ]]);
    }
}
