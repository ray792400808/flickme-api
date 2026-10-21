<?php

namespace App\Http\Middleware;

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
            return response()->json(['message' => '未登录'], 401);
        }

        if ($codes === [] || $user->hasPermission(...$codes)) {
            return $next($request);
        }

        return response()->json([
            'message' => '没有执行此操作的权限',
            'required' => $codes,
        ], 403);
    }
}