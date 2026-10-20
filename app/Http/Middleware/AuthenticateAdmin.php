<?php

namespace App\Http\Middleware;

use App\Models\AdminAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $session = $token ? Cache::get('admin:tokens:'.hash('sha256', $token)) : null;
        $account = is_array($session) ? AdminAccount::query()->find($session['id'] ?? null) : null;
        if (! $account || $account->status !== 'active' || $account->token_version !== ($session['version'] ?? null)) {
            return response()->json(['message' => '请重新登录管理员账号'], 401);
        }
        $request->setUserResolver(fn () => $account);

        return $next($request);
    }
}
