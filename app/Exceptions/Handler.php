<?php

namespace App\Exceptions;

use App\Common\ResponseDef;
use App\Common\ResponseUtil;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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

        $this->renderable(function (Throwable $e, Request $request): ?Response {
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

        $log = "[{$traceId}] SQL Error: ".$e->getMessage().
            ' | Code: '.$e->getCode().
            ' | File: '.$e->getFile().
            ' | Line: '.$e->getLine();

        // 获取SQL信息
        if (method_exists($e, 'getSql')) {
            $log .= ' | SQL: '.$e->getSql();
        }

        Log::channel('sql_error')->error($log, [
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * 处理API异常 — 统一输出 {code, message, data, trace_id}
     */
    private function handleApiException(Request $request, Throwable $e): Response
    {
        if ($e instanceof ValidationException) {
            return ResponseUtil::validationFailed($e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return ResponseUtil::unauthenticated(ResponseDef::MSG_TOKEN_INVALID);
        }

        if ($e instanceof NotFoundHttpException) {
            return ResponseUtil::notFound(ResponseDef::MSG_NOT_FOUND);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $msg = $status >= ResponseDef::CODE_SERVER_ERROR
                ? ResponseDef::MSG_SERVER_ERROR
                : ($e->getMessage() ?: ResponseDef::MSG_BAD_REQUEST);

            // abort(4xx, '业务文案') 这类场景：状态码直接用作业务 code
            $code = $status >= 400 && $status < 600 ? $status : ResponseDef::CODE_SERVER_ERROR;

            $response = ResponseUtil::error($code, $msg, $status);
            $response->headers->add($e->getHeaders());

            return $response;
        }

        // 兜底 500：生产环境不外泄异常细节
        return ResponseUtil::error(ResponseDef::CODE_SYSTEM_ERROR, ResponseDef::MSG_SERVER_ERROR, ResponseDef::CODE_SERVER_ERROR);
    }

    /**
     * trace_id 由 TraceIdMiddleware 写入 request attribute；
     * CLI / 队列等无请求上下文的场景返回 null。
     */
    private function currentTraceId(): ?string
    {
        try {
            return request()?->attributes?->get('trace_id');
        } catch (Throwable) {
            return null;
        }
    }
}
