<?php

namespace App\Common;

use Symfony\Component\HttpFoundation\Response;

/**
 * 统一响应工厂。
 *
 * 调用方约定：
 *   - 所有需要走 JSON 的接口最终都通过本类输出，禁止直接 `response()->json(...)`。
 *   - 成功用 success($data)；失败用 error($code, $msg)；鲜有场景（主动改 HTTP 状态码）用通用 json()。
 *   - 不在 message 里夹 trace id / SQL / 堆栈；
 *     跟踪信息统一走 ResponseDef 的 trace_id 字段（旧版兼容）+ log。
 */
class ResponseUtil
{
    /**
     * 成功响应（默认 200）。
     *
     * @param  mixed  $data   任意可序列化数据；为 null 时返回 "data": null
     * @param  string $msg    业务文案，默认 ok
     */
    public static function success(mixed $data = null, string $msg = ResponseDef::MSG_SUCCESS): Response
    {
        return self::json(ResponseDef::CODE_SUCCESS, $msg, $data, 200);
    }

    /**
     * 失败响应 — 用预设的 code + msg。
     *
     * @param  int    $code      业务码（参考 ResponseDef）
     * @param  string $msg       业务文案（可重写）
     * @param  int    $httpStatus HTTP 状态码
     * @param  mixed  $data      额外数据，比如校验失败时的 errors[]
     */
    public static function error(
        int $code,
        string $msg,
        int $httpStatus = 400,
        mixed $data = null,
    ): Response {
        // 客户端契约：HTTP 状态码与业务 code 默认一致，但允许解耦（如限流 429 仍用 429 code）
        return self::json($code, $msg, $data, $httpStatus);
    }

    /**
     * 通用 JSON：除非明确知道要做什么，否则不要直接调。
     *
     * @param  int    $code        业务码（成功 = 200）
     * @param  string $msg         业务文案
     * @param  mixed  $data        业务载荷，可以是任意结构
     * @param  int    $httpStatus  HTTP 状态码
     */
    public static function json(int $code, string $msg, mixed $data = null, int $httpStatus = 200): Response
    {
        $body = [
            'code' => $code,
            'message' => $msg,
        ];

        // 兼容 null：保留 "data" 键，但携带 null；这样前端类型稳定
        $body['data'] = $data;

        $traceId = function_exists('get_trace_id') ? get_trace_id() : null;
        if ($traceId !== null) {
            $body['trace_id'] = $traceId;
        }

        return new Response(
            $httpStatus,
            ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * 校验失败的快捷方法。
     *
     * @param  array<string, array<int, string>>  $errors  Laravel Validator::errors() 的结构
     */
    public static function validationFailed(array $errors, string $msg = ResponseDef::MSG_UNPROCESSABLE): Response
    {
        return self::json(
            ResponseDef::CODE_UNPROCESSABLE_ENTITY,
            $msg,
            ['errors' => $errors],
            422,
        );
    }

    /**
     * 未认证的快捷方法。
     */
    public static function unauthenticated(string $msg = ResponseDef::MSG_TOKEN_MISSING): Response
    {
        return self::json(ResponseDef::CODE_UNAUTHORIZED, $msg, null, 401);
    }

    /**
     * 无权限的快捷方法。
     */
    public static function forbidden(string $msg = ResponseDef::MSG_PERMISSION_DENIED): Response
    {
        return self::json(ResponseDef::CODE_FORBIDDEN, $msg, null, 403);
    }

    /**
     * 资源不存在的快捷方法。
     */
    public static function notFound(string $msg = ResponseDef::MSG_NOT_FOUND): Response
    {
        return self::json(ResponseDef::CODE_NOT_FOUND, $msg, null, 404);
    }
}
