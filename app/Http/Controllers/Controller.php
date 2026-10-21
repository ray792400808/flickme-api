<?php

namespace App\Http\Controllers;

use App\Common\ResponseDef;
use App\Common\ResponseUtil;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * 控制器基类。
 *
 * 提供统一的成功 / 失败响应快捷方法。
 * 子类控制器建议优先使用本类提供的方法，禁止直接 `response()->json(...)`
 * （个别 log / health 探针接口可豁免）。
 */
abstract class Controller
{
    /** 成功响应 */
    protected function success(mixed $data = null, string $msg = ResponseDef::MSG_SUCCESS): HttpResponse
    {
        return ResponseUtil::success($data, $msg);
    }

    /**
     * 失败响应。
     *
     * @param  int    $code      业务码（见 App\Common\ResponseDef）
     * @param  string $msg       业务文案
     * @param  int    $httpStatus HTTP 状态码
     */
    protected function error(
        int $code,
        string $msg,
        int $httpStatus = 400,
        mixed $data = null,
    ): HttpResponse {
        return ResponseUtil::error($code, $msg, $httpStatus, $data);
    }

    /**
     * 校验失败响应。
     *
     * @param  array<string, array<int, string>>  $errors
     */
    protected function validationFailed(array $errors, string $msg = ResponseDef::MSG_UNPROCESSABLE): HttpResponse
    {
        return ResponseUtil::validationFailed($errors, $msg);
    }

    /** 未认证（401） */
    protected function unauthenticated(string $msg = ResponseDef::MSG_TOKEN_MISSING): HttpResponse
    {
        return ResponseUtil::unauthenticated($msg);
    }

    /** 无权限（403） */
    protected function forbidden(string $msg = ResponseDef::MSG_PERMISSION_DENIED): HttpResponse
    {
        return ResponseUtil::forbidden($msg);
    }

    /** 资源不存在（404） */
    protected function notFound(string $msg = ResponseDef::MSG_NOT_FOUND): HttpResponse
    {
        return ResponseUtil::notFound($msg);
    }
}