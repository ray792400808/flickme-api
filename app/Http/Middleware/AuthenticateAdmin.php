<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['message' => '缺少访问令牌'], 401);
        }

        $cacheKey = 'admin:token:'.hash('sha256', $token);
        $session = Cache::get($cacheKey);

        if (! $session) {
            return response()->json(['message' => '登录状态已失效，请重新登录'], 401);
        }

        $user = AdminUser::query()->find($session['id'] ?? null);

        if (! $user) {
            Cache::forget($cacheKey);

            return response()->json(['message' => '账号不存在'], 401);
        }

        if (! $user->status) {
            return response()->json(['message' => '账号已被禁用'], 403);
        }

        // 角色或账号状态变更后，令牌版本对不上即强制重新登录
        if ($user->tokenVersion() !== ($session['version'] ?? null)) {
            Cache::forget($cacheKey);

            return response()->json(['message' => '权限已变更，请重新登录'], 401);
        }

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('admin_token', $token);

        return $next($request);
    }
}
