<?php

namespace App\Http\Middleware;

use App\Models\AdminOperationLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogAdminOperation
{
    /** 脱敏字段 */
    private const SENSITIVE = ['password', 'old_password', 'new_password', 'password_confirmation', 'token', 'secret'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user) {
            try {
                AdminOperationLog::query()->create([
                    'admin_user_id' => $user->id,
                    'username' => $user->name,
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                    'input' => $this->mask($request->except(['password', 'password_confirmation'])),
                    'description' => $this->describe($request, $response),
                    'trace_id' => $request->attributes->get('trace_id', $this->generateTraceId()),
                ]);
            } catch (\Throwable $e) {
                // 日志失败不能影响业务
                Log::warning('AdminOperationLog 写入失败: '.$e->getMessage());
            }
        }

        return $response;
    }

    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function mask(array $input): array
    {
        foreach (self::SENSITIVE as $key) {
            if (isset($input[$key])) {
                $input[$key] = '******';
            }
        }

        return $input;
    }

    private function describe(Request $request, Response $response): string
    {
        $verb = match ($request->method()) {
            'GET' => '查看',
            'POST' => '创建',
            'PUT', 'PATCH' => '更新',
            'DELETE' => '删除',
            default => '操作',
        };

        return $verb.' '.$request->path().' → '.$response->getStatusCode();
    }
}
