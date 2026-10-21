<?php

namespace App\Common;

/**
 * 统一响应结构定义：code + message + 可选 data。
 *
 * 设计意图（与 `app/Common/ResponseUtil.php` 配合使用）：
 *   - 前端靠 `code` 判断业务成功/失败，不依赖 HTTP 状态码；
 *     HTTP 状态码只用于网关层的可观测性与缓存命中区分。
 *   - `code` 的取值约定：
 *       200            业务成功
 *       4xx / 5xx      业务失败，code 与 HTTP 状态码一致，方便排查
 *       1xxx           公共业务错误（参数缺失、未授权、限流等）
 *   - `message` 给前端直接展示或写入日志；不要把堆栈、SQL 写到 message 里。
 */
class ResponseDef
{
    // ─── 业务 code（与 HTTP 状态码一致）──────────────
    public const CODE_SUCCESS = 200;

    public const CODE_BAD_REQUEST = 400;
    public const CODE_UNAUTHORIZED = 401;
    public const CODE_FORBIDDEN = 403;
    public const CODE_NOT_FOUND = 404;
    public const CODE_METHOD_NOT_ALLOWED = 405;
    public const CODE_CONFLICT = 409;
    public const CODE_GONE = 410;
    public const CODE_UNPROCESSABLE_ENTITY = 422;
    public const CODE_TOO_MANY_REQUESTS = 429;

    public const CODE_SERVER_ERROR = 500;
    public const CODE_NOT_IMPLEMENTED = 501;
    public const CODE_BAD_GATEWAY = 502;
    public const CODE_SERVICE_UNAVAILABLE = 503;

    // ─── 公共业务 code（与 HTTP 状态码独立）────────────
    // 1xxxx：客户端可重试的输入错误
    public const CODE_MISSING_PARAMS = 1001;
    public const CODE_INVALID_PARAMS = 1002;
    public const CODE_REVISION_STALE = 1003;     // 乐观锁冲突

    // 2xxxx：认证 / 授权
    public const CODE_LOGIN_FAILED = 2001;
    public const CODE_ACCOUNT_DISABLED = 2002;
    public const CODE_ACCOUNT_NOT_ROLED = 2003;
    public const CODE_TOKEN_INVALID = 2004;
    public const CODE_TOKEN_EXPIRED = 2005;
    public const CODE_PERMISSION_DENIED = 2006;
    public const CODE_TOKEN_VERSION_MISMATCH = 2007;
    public const CODE_OLD_PASSWORD_ERROR = 2008;

    // 3xxxx：业务资源错误
    public const CODE_USER_NOT_FOUND = 3001;
    public const CODE_ROLE_NOT_FOUND = 3002;
    public const CODE_PERMISSION_NOT_FOUND = 3003;
    public const CODE_MENU_NOT_FOUND = 3004;
    public const CODE_OPERATION_LOG_NOT_FOUND = 3005;
    public const CODE_OPERATION_CONFIG_NOT_FOUND = 3006;
    public const CODE_DATA_NOT_FOUND = 3099;

    // 4xxxx：业务规则错误
    public const CODE_CANNOT_OPERATE_SELF = 4001;   // 不能停用 / 删除自己
    public const CODE_SUPER_ADMIN_PROTECTED = 4002; // 超级管理员角色不可停用
    public const CODE_DUPLICATE = 4003;             // 唯一约束冲突
    public const CODE_HAS_REFERENCES = 4004;        // 还有被引用的记录，禁止删除
    public const CODE_CONFIG_INVALID = 4005;        // 配置草稿校验失败
    public const CODE_PUBLISH_FAILED = 4006;        // 配置发布失败

    // 5xxxx：系统错误
    public const CODE_SYSTEM_ERROR = 5000;
    public const CODE_DB_ERROR = 5001;
    public const CODE_REDIS_ERROR = 5002;

    // ─── message 文案 ─────────────────────────────────
    public const MSG_SUCCESS = 'ok';

    // 通用
    public const MSG_BAD_REQUEST = '请求无效';
    public const MSG_NOT_FOUND = '资源不存在';
    public const MSG_METHOD_NOT_ALLOWED = '请求方法不被允许';
    public const MSG_CONFLICT = '资源冲突';
    public const MSG_UNPROCESSABLE = '请求参数校验失败';
    public const MSG_TOO_MANY_REQUESTS = '请求过于频繁，请稍后再试';
    public const MSG_SERVER_ERROR = '服务异常，请稍后再试';
    public const MSG_SERVICE_UNAVAILABLE = '服务暂不可用';

    // 公共输入
    public const MSG_MISSING_PARAMS = '缺少必填参数';
    public const MSG_INVALID_PARAMS = '参数校验失败';
    public const MSG_REVISION_STALE = '数据已被更新，请刷新后重试';

    // 认证 / 授权
    public const MSG_LOGIN_FAILED = '邮箱或密码错误';
    public const MSG_ACCOUNT_DISABLED = '账号已被禁用，请联系超级管理员';
    public const MSG_ACCOUNT_NOT_ROLED = '账号尚未分配角色，无法登录';
    public const MSG_TOKEN_MISSING = '缺少访问令牌';
    public const MSG_TOKEN_INVALID = '登录状态已失效，请重新登录';
    public const MSG_TOKEN_EXPIRED = '登录已过期，请重新登录';
    public const MSG_PERMISSION_DENIED = '无权访问';
    public const MSG_TOKEN_VERSION_MISMATCH = '权限已变更，请重新登录';
    public const MSG_OLD_PASSWORD_ERROR = '原密码不正确';

    // 资源不存在
    public const MSG_USER_NOT_FOUND = '账号不存在';
    public const MSG_ROLE_NOT_FOUND = '角色不存在';
    public const MSG_PERMISSION_NOT_FOUND = '权限不存在';
    public const MSG_MENU_NOT_FOUND = '菜单不存在';
    public const MSG_OPERATION_LOG_NOT_FOUND = '操作日志不存在';
    public const MSG_OPERATION_CONFIG_NOT_FOUND = '操作配置不存在';

    // 业务规则
    public const MSG_CANNOT_OPERATE_SELF = '不能对当前登录的账号做此操作';
    public const MSG_SUPER_ADMIN_PROTECTED = '超级管理员角色不能停用';
    public const MSG_DUPLICATE = '数据已存在';
    public const MSG_HAS_REFERENCES = '仍有被引用的记录，禁止删除';
    public const MSG_CONFIG_INVALID = '配置草稿校验失败';
    public const MSG_PUBLISH_FAILED = '配置发布失败';
}
