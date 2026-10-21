<?php

namespace App\Exceptions;

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
        $traceId = function_exists('get_trace_id') ? get_trace_id() : 'N/A';

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
     * 处理API异常
     */
    private function handleApiException(Request $request, Throwable $e): JsonResponse
    {
        $traceId = function_exists('get_trace_id') ? get_trace_id() : 'N/A';

        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'trace_id' => $traceId,
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'trace_id' => $traceId,
            ], 401);
        }

        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
                'trace_id' => $traceId,
            ], 404);
        }

        if ($e instanceof HttpException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'HTTP Error',
                'trace_id' => $traceId,
            ], $e->getStatusCode());
        }

        // 默认错误响应（包含trace_id）
        $debug = config('app.debug');

        return response()->json([
            'success' => false,
            'message' => $debug ? $e->getMessage() : 'Internal server error',
            'trace_id' => $traceId,
            'debug' => $debug ? [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $debug ? $e->getTraceAsString() : null,
            ] : null,
        ], 500);
    }
}
