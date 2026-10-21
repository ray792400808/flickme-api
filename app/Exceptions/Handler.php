<?php

namespace App\Exceptions;

use App\Common\ResponseDef;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e): void {
            // 记录SQL错误到专门的日志通道
            if ($e instanceof QueryException) {
                $this->logSqlError($e);
            }
        });

        $this->renderable(function (Throwable $e, Request $request): ?JsonResponse {
            if ($request->is('v1/*', 'admin/*') || $request->expectsJson()) {
                return $this->handleApiException($request, $e);
            }
            return null;
        });
    }

    /**
     * 记录SQL错误
     */
    private function logSqlError(Throwable $e): void
    {
        $traceId = $this->currentTraceId() ?? 'N/A';

        $log = "[{$traceId}] SQL Error: " . $e->getMessage() .
            " | Code: " . $e->getCode() .
            " | File: " . $e->getFile() .
            " | Line: " . $e->getLine();

        // 获取SQL信息
        if (method_exists($e, 'getSql')) {
            $log .= " | SQL: " . $e->getSql();
        }

        Log::channel('sql_error')->error($log, [
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * 处理API异常 — 统一输出 {code, message, data, trace_id}
     */
    private function handleApiException(Request $request, Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return $this->toJson(
                ResponseDef::CODE_UNPROCESSABLE_ENTITY,
                ResponseDef::MSG_UNPROCESSABLE,
                ['errors' => $e->errors()],
                422,
            );
        }

        if ($e instanceof AuthenticationException) {
            return $this->toJson(
                ResponseDef::CODE_UNAUTHORIZED,
                ResponseDef::MSG_TOKEN_INVALID,
                null,
                401,
            );
        }

        if ($e instanceof NotFoundHttpException) {
            return $this->toJson(
                ResponseDef::CODE_NOT_FOUND,
                ResponseDef::MSG_NOT_FOUND,
                null,
                404,
            );
        }

        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            $msg = $e->getMessage() ?: (ResponseDef::MSG_SERVER_ERROR);

            // abort(4xx, '业务文案') 这类场景：状态码直接用作业务 code
            $code = $status >= 400 && $status < 600 ? $status : ResponseDef::CODE_SERVER_ERROR;

            return $this->toJson($code, $msg, null, $status);
        }

        // 兜底 500：生产环境不外泄异常细节
        $debug = config('app.debug');

        return $this->toJson(
            ResponseDef::CODE_SERVER_ERROR,
            $debug ? $e->getMessage() : ResponseDef::MSG_SERVER_ERROR,
            $debug ? [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ] : null,
            500,
        );
    }

    /**
     * 统一输出 {code, message, data, trace_id}
     */
    private function toJson(int $code, string $msg, ?array $data, int $httpStatus): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $msg,
            'data' => $data,
            'trace_id' => $this->currentTraceId(),
        ], $httpStatus);
    }

    /**
     * trace_id 由 TraceIdMiddleware 写入 request attribute；
     * CLI / 队列等无请求上下文的场景返回 null。
     */
    private function currentTraceId(): ?string
    {
        try {
            return request()?->attributes?->get('trace_id');
        } catch (\Throwable) {
            return null;
        }
    }
}