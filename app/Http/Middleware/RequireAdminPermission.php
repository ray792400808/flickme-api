<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminPermission
{
    public function handle(Request $request, Closure $next, string $group, string $level = 'r'): Response
    {
        $permission = $request->user()->profile()['permissions'][$group] ?? null;
        abort_unless($permission === 'rw' || ($level === 'r' && $permission === 'r'), 403, '没有执行此操作的权限');

        return $next($request);
    }
}
