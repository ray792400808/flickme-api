<?php

namespace App\Http\Middleware;

use App\Common\ResponseDef;
use App\Common\ResponseUtil;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    /**
     * 用法: ->middleware(CheckAdminPermission::class.':user.create')
     *       ->middleware(CheckAdminPermission::class.':user.edit,user.delete')
     *
     * 多个权限码之间是「或」关系，满足其一即放行。
     * 超级管理员与 admin.* 通配符由 AdminUser::hasPermission 统一处理。
     */
    public function handle(Request $request, Closure $next, string ...$codes): Response
    {
        $user = $request->user();

        if (! $user) {
            return ResponseUtil::unauthenticated('未登录');
        }

        if ($codes === [] || $user->hasPermission(...$codes)) {
            return $next($request);
        }

        return ResponseUtil::error(
            ResponseDef::CODE_PERMISSION_DENIED,
            ResponseDef::MSG_PERMISSION_DENIED,
            403,
            ['required' => $codes],
        );
    }
}
