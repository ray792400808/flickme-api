<?php

namespace App\Common;

/**
 * 通用业务常量。
 *
 * 编排原则：
 *  - 业务枚举值集中在这里，禁止在控制器 / Service 中写 magic number。
 *  - 已有数据库里写死成数字的字段，值要与下面常量 1:1 对齐，绝不允许单边修改。
 *  - 新增常量按类别分组，不要把不同语义的常量混在同一段。
 */
class Constants
{
    // ─── 业务状态 ────────────────────────────────────
    // 通用启停位（admin_users.status、admin_roles.status 等通用逻辑）
    public const STATUS_ENABLED = 1;

    public const STATUS_DISABLED = 0;

    // ─── 后台账户相关 ────────────────────────────────
    public const ADMIN_TOKEN_CACHE_TTL = 60 * 60 * 12; // 12 小时

    public const ADMIN_PERMISSION_SUPER = '*';           // 超管通配

    public const ADMIN_PERMISSION_TYPE_MENU = 1;

    public const ADMIN_PERMISSION_TYPE_BUTTON = 2;

    public const ADMIN_PERMISSION_TYPE_API = 3;

    // ─── 操作日志保留策略 ──────────────────────────
    public const OPERATION_LOG_RETENTION_DAYS = 180;

    public const OPERATION_LOG_METHOD_GET = 'GET';

    public const OPERATION_LOG_METHOD_POST = 'POST';

    public const OPERATION_LOG_METHOD_PUT = 'PUT';

    public const OPERATION_LOG_METHOD_PATCH = 'PATCH';

    public const OPERATION_LOG_METHOD_DELETE = 'DELETE';

    // ─── 业务变更操作类型 (audit 写入用) ─────────
    public const AUDIT_OP_LOGIN = 'admin.login';

    public const AUDIT_OP_LOGOUT = 'admin.logout';

    public const AUDIT_OP_CREATE = 'admin.create';

    public const AUDIT_OP_UPDATE = 'admin.update';

    public const AUDIT_OP_DELETE = 'admin.delete';

    public const AUDIT_OP_RESET_PWD = 'admin.reset_password';

    public const AUDIT_OP_FORCE_LOGOUT = 'admin.force_logout';

    public const AUDIT_OP_SORT = 'admin.sort';

    // ─── 数据库默认值 ──────────────────────────────
    public const DEFAULT_PAGE_SIZE = 15;

    public const MAX_PAGE_SIZE = 100;

    // ─── 角色标识 ────────────────────────────────────
    public const ROLE_SUPER_ADMIN_SLUG = 'super_admin';

    // ─── 菜单类型 ────────────────────────────────────
    public const MENU_TYPE_DIR = 0;       // 目录（无 component）

    public const MENU_TYPE_MENU = 1;      // 真实菜单

    public const MENU_TYPE_BUTTON = 2;    // 后台权限（不进路由）

    // ─── API 客户端契约 ─────────────────────────────
    // 客户端的 request-id header，业务方用于把日志串起来
    public const HEADER_REQUEST_ID = 'X-Request-Id';

    public const HEADER_TRACE_ID = 'X-Trace-Id';
}
