# RBAC 重构说明（v2 — 按钮级权限 + 软删除）

## 概述

从旧的 `admin_accounts`/`admin_roles`（`role_key` 字符串）迁移到 `admin_*` 前缀的标准 RBAC 模型，支持**按钮 / 接口级别**权限控制，所有表均含软删除。

## 数据库结构

### ER 图

```
admin_users  ──── admin_role_user ───── admin_roles
     │                                     │
     └── admin_user_permission             └── admin_role_permission ── admin_permissions
                                                     │
admin_menus ──── admin_role_menu ───────────────────┘
```

### 表清单

| 表名 | 说明 | 关键字段 |
|------|------|----------|
| `admin_users` | 后台用户 | email, password, status, login_ip, login_at |
| `admin_roles` | 角色 | name, **slug**（唯一标识）, status, is_default |
| `admin_permissions` | 权限节点 | **code**（唯一标识，如 `user.create`）, type(1=模块/2=页面/3=按钮), module, parent_id |
| `admin_menus` | 前端路由菜单 | path, component, **permission_id**（访问该页面的权限码）, parent_id |
| `admin_role_user` | 用户-角色 | admin_user_id, admin_role_id |
| `admin_role_permission` | 角色-权限 | admin_role_id, admin_permission_id |
| `admin_role_menu` | 角色-菜单（冗余路由可见性） | admin_role_id, admin_menu_id |
| `admin_user_permission` | 用户额外授权（超越角色） | admin_user_id, admin_permission_id |
| `admin_operation_logs` | 操作日志 | admin_user_id, method, path, ip, input, description |

> **所有表均含** `id`, `created_at`, `updated_at`, `deleted_at`（软删除）。

## 权限模型

### 两层设计

| 层级 | 表 | 控制点 | 示例 |
|------|----|--------|------|
| 菜单 | `admin_menus` | 用户能否**看到并进入**某页面 | 左侧菜单、路由守卫 |
| 权限码 | `admin_permissions` | 用户能否**执行**某操作 | 按钮显隐、接口拦截 |

### 权限码命名规范

```
{module}.{action}
```

| module | 说明 | 动作示例 |
|--------|------|----------|
| user | 用户管理 | user.view / user.create / user.edit / user.delete |
| role | 角色管理 | role.view / role.create / role.edit / role.delete |
| permission | 权限管理 | permission.view / permission.create / … |
| menu | 菜单管理 | menu.view / menu.create / … |
| log | 操作日志 | log.view / log.delete |
| system | 系统设置 | system.view |

### 通配符支持

- `user.*` → 拥有 `user` 模块下所有权限
- `*` → 完全不推荐，等同于超级管理员

## API 路由与权限码对应

| 路由 | 权限码 | 说明 |
|------|--------|------|
| `GET /users` | `user.view` | 列表 |
| `POST /users` | `user.create` | 创建 |
| `GET /users/{id}` | `user.view` | 详情 |
| `PATCH /users/{id}` | `user.edit` | 编辑 |
| `DELETE /users/{id}` | `user.delete` | 删除 |
| `POST /users/{id}/reset-password` | `user.edit` | 重置密码 |
| `GET /roles` | `role.view` | 列表 |
| `POST /roles` | `role.create` | 创建 |
| `PATCH /roles/{id}` | `role.edit` | 编辑 |
| `DELETE /roles/{id}` | `role.delete` | 删除 |
| `GET /permissions` | `permission.view` | 列表 |
| `POST /permissions` | `permission.create` | 创建 |
| `PATCH /permissions/{id}` | `permission.edit` | 编辑 |
| `DELETE /permissions/{id}` | `permission.delete` | 删除 |
| `GET /menus` | `menu.view` | 列表 |
| `POST /menus` | `menu.create` | 创建 |
| `PATCH /menus/{id}` | `menu.edit` | 编辑 |
| `DELETE /menus/{id}` | `menu.delete` | 删除 |
| `GET /operation-logs` | `log.view` | 列表 |
| `DELETE /operation-logs/{id}` | `log.delete` | 删除单条 |
| `POST /operation-logs/purge` | `log.delete` | 批量清理 |

## 部署步骤

```bash
# 1. 迁移
cd flickme-api
php artisan migrate

# 2. 填充初始数据（权限 + 菜单 + 超管）
php artisan db:seed --class=AdminSeeder

# 3. 安装前端依赖
cd flickme-admin
pnpm install

# 4. 启动
php artisan serve          # 后端
pnpm dev                  # 前端
```

## 默认数据

| 类型 | 标识 | 内容 |
|------|------|------|
| 角色 | `super_admin` | 超级管理员（拥有全部权限） |
| 角色 | `operator` | 运营（user.view / role.view / menu.view / log.view） |
| 角色 | `customer_service` | 客服（user.view） |
| 角色 | `finance` | 财务（log.view） |
| 管理员 | `admin@flickme.com` | 密码 `admin123`，关联 super_admin |
| 菜单 | /dashboard | 数据概览（Affix 固定标签页） |
| 菜单 | /system/* | 系统管理目录（隐藏），含 users / roles / permissions / menus / logs |

## 前端用法

```vue
<script setup>
import { useAuthStore } from '@/stores/auth'
const auth = useAuthStore()

// 按钮级权限
auth.can('user.create')  // → boolean
auth.can('user.edit', 'user.delete')  // 满足任一即 true

// 页面级权限
auth.canView('/system/users')  // 能否进入某菜单页

// 超级管理员
auth.isSuperAdmin  // true 直接放行
</script>

<template>
  <!-- 需要 user.create 权限才显示 -->
  <el-button v-if="auth.can('user.create')">新增用户</el-button>

  <!-- v-if + can 组合 -->
  <el-button v-if="auth.can('user.edit', 'user.delete')" type="danger">批量操作</el-button>
</template>
```

## 命令行工具

```bash
# 检查路由权限码是否已注册到 admin_permissions 表
php artisan admin:check-permissions

# 创建初始超管（需先执行 AdminSeeder）
php artisan admin:bootstrap --email=owner@example.com
```

## 注意事项

1. **软删除**：用户/角色被删除后数据仍在库，可通过 `withTrashed()` 恢复
2. **超级管理员保护**：`super_admin` 角色不可删除；只剩一个启用状态超管时禁止降权或删除
3. **改密失效**：修改密码后该账号所有活跃会话立即强制下线
4. **权限码不可重用**：已授权的权限码被修改后，关联的角色会**立即失去**该权限
5. **旧表清理**：迁移完成后手动 `DROP TABLE admin_accounts, admin_roles`
