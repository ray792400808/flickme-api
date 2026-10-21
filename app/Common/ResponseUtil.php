<?php

namespace App\Common;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * 统一响应工厂。
 *
 * 调用方约定：
 *   - 所有需要走 JSON 的接口最终都通过本类输出，禁止直接 `response()->json(...)`。
 *   - 成功用 success($data)；失败用 error($code, $msg)；鲜有场景（主动改 HTTP 状态码）用通用 json()。
 *   - 不在 message 里夹 SQL / 堆栈；跟踪信息统一走 trace_id 字段 + log。
 *
 * 实现说明：
 *   一律使用 Laravel 的 JsonResponse（通过 response()->json()），
 *   不要手写 new SymfonyResponse(...) —— 后者构造函数是
 *   (content, status, headers)，与 response()->json($data, $status) 的参数顺序相反，
 *   极易写错。
 */
class ResponseUtil
{
    /**
     * 成功响应（HTTP 200）。
     *
     * @param  mixed  $data  任意可序列化数据；为 null 时返回 "data": null
     * @param  string  $msg  业务文案，默认 ok
     */
    public static function success(mixed $data = null, string $msg = ResponseDef::MSG_SUCCESS): SymfonyResponse
    {
        return self::json(ResponseDef::CODE_SUCCESS, $msg, $data, 200);
    }

    /**
     * 失败响应 —— 用预设的 code + msg。
     *
     * @param  int  $code  业务码（见 App\Common\ResponseDef）
     * @param  string  $msg  业务文案
     * @param  int  $httpStatus  HTTP 状态码；默认与 $code 保持一致
     * @param  mixed  $data  额外数据，比如校验失败时的 errors[]
     */
    public static function error(int $code, string $msg, ?int $httpStatus = null, mixed $data = null): SymfonyResponse
    {
        // code 落在 4xx/5xx 时，HTTP 状态码默认跟随，方便网关与浏览器识别
        $status = $httpStatus ?? ($code >= 400 && $code < 600 ? $code : SymfonyResponse::HTTP_BAD_REQUEST);

        return self::json($code, $msg, $data, $status);
    }

    /**
     * 通用 JSON。
     *
     * @param  int  $code  业务码（成功 = 200）
     * @param  string  $msg  业务文案
     * @param  mixed  $data  业务载荷
     * @param  int  $httpStatus  HTTP 状态码
     */
    public static function json(int $code, string $msg, mixed $data = null, int $httpStatus = 200): SymfonyResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $msg,
            'data' => self::normalize($data),
            'trace_id' => self::traceId(),
        ], $httpStatus);
    }

    /**
     * 校验失败（HTTP 422）。
     *
     * @param  array<string, array<int, string>>  $errors  Laravel Validator::errors() 的结构
     */
    public static function validationFailed(array $errors, string $msg = ResponseDef::MSG_UNPROCESSABLE): SymfonyResponse
    {
        return self::json(
            ResponseDef::CODE_UNPROCESSABLE_ENTITY,
            $msg,
            ['errors' => $errors],
            422,
        );
    }

    /** 未认证（HTTP 401） */
    public static function unauthenticated(string $msg = ResponseDef::MSG_TOKEN_MISSING): SymfonyResponse
    {
        return self::json(ResponseDef::CODE_UNAUTHORIZED, $msg, null, 401);
    }

    /** 无权限（HTTP 403） */
    public static function forbidden(string $msg = ResponseDef::MSG_PERMISSION_DENIED): SymfonyResponse
    {
        return self::json(ResponseDef::CODE_FORBIDDEN, $msg, null, 403);
    }

    /** 资源不存在（HTTP 404） */
    public static function notFound(string $msg = ResponseDef::MSG_NOT_FOUND): SymfonyResponse
    {
        return self::json(ResponseDef::CODE_NOT_FOUND, $msg, null, 404);
    }

    // ─── 内部 ───────────────────────────────────────────────

    /**
     * trace_id 来自 TraceIdMiddleware 写入的 request attribute。
     * 拿不到（如 CLI、队列任务）时返回 null，前端据此忽略该字段。
     */
    private static function traceId(): ?string
    {
        try {
            $request = request();

            return $request?->attributes?->get('trace_id');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 保证 data 是可 JSON 序列化的结构。
     * Eloquent Collection / Arrayable / JsonSerializable 直接透传，交给 Laravel 处理。
     */
    private static function normalize(mixed $data): mixed
    {
        if ($data === null || is_array($data) || $data instanceof JsonSerializable) {
            return $data;
        }

        if ($data instanceof Arrayable) {
            return $data->toArray();
        }

        if (is_object($data)) {
            return get_object_vars($data);
        }

        return $data;
    }
}
