<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAccount;
use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:254'], 'password' => ['required', 'string', 'max:1024']]);
        $account = AdminAccount::query()->where('email', strtolower($data['email']))->first();
        $valid = Hash::check($data['password'], $account?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $account || $account->status !== 'active' || ! $valid) {
            return response()->json(['message' => '邮箱或密码错误'], 401);
        }
        $token = bin2hex(random_bytes(32));
        $expires = now()->addMinutes(config('admin.token_minutes'));
        Cache::put('admin:tokens:'.hash('sha256', $token), ['id' => $account->id, 'version' => $account->token_version], $expires);
        $account->update(['last_login_at' => now()]);

        return response()->json(['data' => ['accessToken' => $token, 'expiresAt' => $expires->getTimestampMs(), 'email' => $account->email]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->profile()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Cache::forget('admin:tokens:'.hash('sha256', (string) $request->bearerToken()));

        return response()->json(['data' => ['loggedOut' => true]]);
    }

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64'], 'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed']]);
        DB::transaction(function () use ($data): void {
            $account = AdminAccount::query()->where('activation_hash', hash('sha256', $data['token']))->lockForUpdate()->first();
            abort_unless($account && $account->status === 'pending' && $account->activation_expires_at?->isFuture(), 422, '链接无效或已过期');
            $account->update(['password' => $data['password'], 'status' => 'active', 'activation_hash' => null, 'activation_expires_at' => null, 'token_version' => $account->token_version + 1]);
            AdminAuditLog::query()->create(['actor_id' => $account->id, 'actor' => $account->email, 'action' => '激活账号或设置密码', 'subject' => $account->id.' · '.$account->email]);
        });

        return response()->json(['data' => ['activated' => true]]);
    }
}
