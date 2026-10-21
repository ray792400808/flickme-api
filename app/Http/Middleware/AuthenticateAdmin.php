<?php

namespace App\Http\Middleware;

use App\Common\ResponseDef;
use App\Common\ResponseUtil;
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
            return $this->deny(ResponseDef::CODE_UNAUTHORIZED, ResponseDef::MSG_TOKEN_MISSING);
        }

        $cacheKey = 'admin:token:'.hash('sha256', $token);
        $session = Cache::get($cacheKey);

        if (! $session) {
            return $this->deny(ResponseDef::CODE_UNAUTHORIZED, ResponseDef::MSG_TOKEN_INVALID);
        }

        $user = AdminUser::query()->find($session['id'] ?? null);

        if (! $user) {
            Cache::forget($cacheKey);

            return $this->deny(ResponseDef::CODE_UNAUTHORIZED, ResponseDef::MSG_USER_NOT_FOUND);
        }

        if (! $user->status) {
            return $this->deny(ResponseDef::CODE_FORBIDDEN, ResponseDef::MSG_ACCOUNT_DISABLED);
        }

        // 角色或账号状态变更后，令牌版本对不上即强制重新登录
        if ($user->tokenVersion() !== ($session['version'] ?? null)) {
            Cache::forget($cacheKey);

            return $this->deny(ResponseDef::CODE_UNAUTHORIZED, ResponseDef::MSG_TOKEN_VERSION_MISMATCH);
        }

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('admin_token', $token);

        return $next($request);
    }

    /**
     * 认证失败统一走统一响应体，前端凭 code 区分「需要重新登录」还是「账号被禁用」。
     */
    private function deny(int $code, string $msg): Response
    {
        $httpStatus = $code === ResponseDef::CODE_FORBIDDEN ? 403 : 401;

        return ResponseUtil::error($code, $msg, $httpStatus);
    }
}
