<?php

namespace App\Http\Middleware;

use App\Common\Constants;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TraceIdMiddleware
{
    /**
     * 处理请求，设置 Trace-Id 并添加到响应头
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 获取或生成 trace_id
        $traceId = $this->getTraceId($request);

        // 添加到请求属性，方便后续使用
        $request->attributes->set('trace_id', $traceId);

        // 处理请求
        $response = $next($request);

        // 将 trace_id 添加到响应头
        $response->headers->set('Trace-Id', $traceId);
        $response->headers->set(Constants::HEADER_TRACE_ID, $traceId);

        return $response;
    }

    /**
     * 获取追踪ID
     * 优先从请求header获取，其次从请求参数获取，都没有则自动生成
     */
    private function getTraceId(Request $request): string
    {
        // 1. 优先从请求header获取
        $traceId = $request->header(Constants::HEADER_TRACE_ID)
            ?: $request->header(Constants::HEADER_REQUEST_ID)
            ?: $request->header('Trace-Id');
        if ($traceId) {
            return $traceId;
        }

        // 2. 从请求参数获取 (query/POST)
        $traceId = $request->input('trace_id');
        if ($traceId) {
            return $traceId;
        }

        // 3. 自动生成32位唯一ID
        return Str::random(32);
    }
}
