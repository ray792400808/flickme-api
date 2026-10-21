<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestLogMiddleware
{
    /**
     * 不记录body的路径
     */
    private array $notRecordBodyUrl = [
        '/v1/common/upload',
        '/v1/common/upload/video/slice',
    ];

    /**
     * 处理请求，记录访问日志
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->logRequest($request, $response);

        return $response;
    }

    private function logRequest(Request $request, Response $response): void
    {
        $traceId = $request->attributes->get('trace_id', 'N/A');
        $requestIp = $request->ip();
        $url = $request->path();
        $statusCode = $response->getStatusCode();
        $method = $request->method();
        $bodyParams = $this->sanitizeParams($request->all());
        $headers = json_encode($this->sanitizeHeaders($request->header()), JSON_UNESCAPED_UNICODE);

        // 判断是否记录body
        if (in_array('/' . $url, $this->notRecordBodyUrl)) {
            $rawBody = '[skip-record]';
        } else {
            $rawBody = preg_replace('/\s+/', ' ', $request->getContent());
            if (strlen($rawBody) > 2000) {
                $rawBody = substr($rawBody, 0, 2000) . '...[truncated]';
            }
        }

        $responseBody = $response->getContent();
        if (strlen($responseBody) > 2000) {
            $responseBody = substr($responseBody, 0, 2000) . '...[truncated]';
        }

        $log = "[{$traceId}] [{$requestIp}] URL: '{$url}', STATUS: '{$statusCode}', METHOD: '{$method}', PARAMS: '" . json_encode($bodyParams, JSON_UNESCAPED_UNICODE) . "', RAW_BODY: '{$rawBody}', HEADERS: '{$headers}', RESPONSE: '{$responseBody}'";

        Log::channel('access')->info($log);
    }

    private function sanitizeParams(array $params): array
    {
        $sensitive = ['password', 'old_password', 'new_password', 'password_confirmation', 'token', 'secret', 'api_key', 'apiSecret'];

        foreach ($params as $key => $value) {
            if (in_array(strtolower($key), $sensitive)) {
                $params[$key] = '******';
            }
        }

        return $params;
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'x-api-key', 'x-api-secret'];

        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitive)) {
                $headers[$key] = is_array($value) ? ['******'] : ['******'];
            }
        }

        return $headers;
    }
}
