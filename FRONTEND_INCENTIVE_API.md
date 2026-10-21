# 激励任务三页面前端对接文档

本文对应当前后端实现：签到奖励、任务配置、实时观看奖励。接口定义另见 `apifox-admin-api.yaml`。

## 1. 给前端 agent 的任务

接入真实接口，替换三个页面的模拟数据和仅在本地保存的操作。保留原型布局，复用当前认证、权限、请求封装和动态菜单体系，不重复建设页面或路由。

前端仓库为 `D:\tv_web`。读取时发现以下组件映射已经存在，请先检查它们的实际实现再修改：

| 页面 | 已注册组件 |
| --- | --- |
| 签到奖励 | `src/views/tasks/CheckinView.vue` |
| 任务配置 | `src/views/tasks/TaskConfigView.vue` |
| 实时观看奖励 | `src/views/tasks/WatchRewardsView.vue` |

参考原型：`D:\tv_pro\Flickme_Admin.html`，以及前端 `src/features/workspace/runtime.js` 中的 `checkin`、`taskcfg`、`watch`。

本次只对接后台配置，不实现客户端签到领取、播放时长上报、任务完成判定或奖励入账。页面说明中的七日循环、断签重置、Bonus 有效期等是业务规则说明，不能据此认为客户端结算接口已实现。

## 2. 地址与请求封装

- 后端本地地址：`http://localhost:8000/admin`。
- 前端开发环境使用同源 `/admin`，由 `vite.config.ts` 代理至后端。不要把 `8000` 硬编码进页面。
- 本文请求路径已包含 `/admin`；若调用现有 `requestApi()`，只传 `/tasks` 等相对路径，避免 `/admin/admin/tasks`。
- 当前路径不含 `/v1`，不要请求 `/admin/v1/tasks`。
- 请求有 JSON body 时发送 `Content-Type: application/json`，并发送 `Accept: application/json`。
- 所有接口需要 `Authorization: Bearer <token>`，复用现有登录态。
- 可选追踪头为 `X-Trace-Id`，后端也兼容旧头 `Trace-Id`。
- 发布请求额外需要 `Idempotency-Key`。当前 `requestApi()` 的请求头扩展能力需检查；如果尚不支持，应以向后兼容方式扩展，不能丢失认证头或追踪头。

请求体直接提交业务字段，不套 `data`、`code`、`message` 外层。

成功响应如下，HTTP 状态为 200，包括新增和删除：

```json
{
  "code": 200,
  "message": "ok",
  "data": {
    "revision": 1,
    "published_revision": null,
    "published_at": null,
    "draft": [],
    "published": null
  }
}
```

`trace_id` 是可选响应字段，不能依赖其必定存在。现有 `requestApi<T>()` 已解包返回 `data`，页面不要再多取一次 `.data`；直接 `fetch` 才需要解析完整响应。

## 3. 通用配置类型与状态

```ts
type TaskGroup = '新手任务' | '观看任务' | '日常任务';
type TaskReset = '一次性' | '每日';

interface CheckinDay {
  id: string;      // CK1 ～ CK7
  day: string;     // Day 1 ～ Day 7
  reward: number;
}

interface IncentiveTask {
  id: string;
  group: TaskGroup;
  name: string;
  sec?: number;    // 仅观看任务存在
  reward: number;
  reset: TaskReset;
  en: boolean;
}

type TaskInput = Omit<IncentiveTask, 'id' | 'name'> & { name?: string };

interface WatchReward {
  sec: number;
  coins: number;
  cap: number;
}

interface ConfigDocument<T> {
  revision: number;
  published_revision: number | null;
  published_at: number | null;
  draft: T;
  published: T | null;
}
```

三个资源各自维护独立的版本号。任务的 `revision` 是整份任务列表的版本，不是某一行的版本。

| 字段 | 含义 |
| --- | --- |
| `revision` | 当前草稿版本，每次成功保存、新增、修改或删除加 1 |
| `published_revision` | 最近发布的版本，未发布为 null |
| `published_at` | 最近发布时间，Unix 秒级整数，未发布为 null |
| `draft` | 当前可编辑配置；任务 GET 带筛选时仅此字段被筛选 |
| `published` | 上次发布的完整配置快照，未发布为 null |

发布时间展示用 `new Date(published_at * 1000)`，先处理 null。初始默认值只有草稿，不代表已经发布。

签到和任务的状态可由版本判断：`published_revision === null` 为未发布；两版本不同为存在未发布草稿；相等为当前版本已发布。表单本地修改尚未保存时，另设 `isDirty`，不能只比较服务端版本号。

## 4. 签到奖励

### 读取

`GET /admin/check-in-rewards`

返回 `ConfigDocument<CheckinDay[]>`。默认奖励依次为 `[10, 15, 20, 25, 30, 50, 100]`。

### 保存草稿

`PUT /admin/check-in-rewards`

必须提交完整七天配置，不能仅提交修改的某一天：

```json
{
  "revision": 1,
  "draft": [
    {"id": "CK1", "day": "Day 1", "reward": 10},
    {"id": "CK2", "day": "Day 2", "reward": 15},
    {"id": "CK3", "day": "Day 3", "reward": 20},
    {"id": "CK4", "day": "Day 4", "reward": 25},
    {"id": "CK5", "day": "Day 5", "reward": 30},
    {"id": "CK6", "day": "Day 6", "reward": 50},
    {"id": "CK7", "day": "Day 7", "reward": 100}
  ]
}
```

规则：固定七行、固定顺序，`id` 和 `day` 只读，不能新增、删除或重排。每行只提交 `id/day/reward`。`reward` 为 0～1,000,000 的整数。额度上限取后端 `wallet.adjustment_limit`，当前配置为 1,000,000。

保存成功返回完整文档及新版本，例如 `revision: 2`，但 `published` 不变。UI 文案应为“草稿已保存”。

### 发布

`POST /admin/check-in-rewards/publish`

```http
Idempotency-Key: <本次发布操作的唯一标识>
```

```json
{"revision": 2}
```

使用保存成功返回的最新版本。发布不增加 `revision`，会更新 `published`、`published_revision`、`published_at`。若提供“保存并发布”按钮，必须先等待 PUT 成功，再拿新版本发 POST。

## 5. 任务配置

### 读取、搜索与分组

`GET /admin/tasks`

可选查询参数：

| 参数 | 规则 |
| --- | --- |
| `group` | 精确取值：`新手任务`、`观看任务`、`日常任务`；“全部”时省略该参数 |
| `q` | 任务名称子串搜索，不区分大小写，最长 100 字符 |

使用 `URLSearchParams` 编码中文。筛选参数可组合，无分页，返回 `ConfigDocument<IncentiveTask[]>`。

初始共 13 条任务：5 条新手、5 条观看、3 条日常。初始任务 ID 为 `TK1` 等，新任务为服务端生成的 UUID；全部按不透明字符串处理，不解析其格式。

筛选只影响 `draft`，`published` 仍是完整发布快照，版本号仍是整份列表的版本。发布会发布全部任务，不是仅发布当前筛选结果。列表计数应取 `draft.length`。

### 新增

`POST /admin/tasks`

```json
{
  "revision": 1,
  "task": {
    "group": "观看任务",
    "sec": 240,
    "reward": 12,
    "reset": "每日",
    "en": true
  }
}
```

不要提交 `id`。服务端生成 ID，并生成 `name: "Watch dramas for 4 min"`。新增成功返回完整任务文档，不是仅返回新增行。

### 修改、启停

`PATCH /admin/tasks/{taskId}`

```json
{"revision": 2, "task": {"reward": 15, "en": false}}
```

支持部分字段更新；启用开关复用此接口，提交 JSON 布尔值。不要把整行对象直接作为 `task` 提交，因为输入对象不允许包含 `id`。切换分组时，补齐新分组需要的字段。

### 删除

`DELETE /admin/tasks/{taskId}`

版本号放在 JSON body 中，删除也需要最新版本：

```json
{"revision": 3}
```

返回删除后的完整文档及新版本，不是 204。删除只改草稿，发布前线上快照不变。删除不存在的任务返回 HTTP 404（前提是请求版本仍有效）。

### 发布

`POST /admin/tasks/publish`

携带 `Idempotency-Key`，body 为 `{"revision": 4}`。行为与签到发布一致。

### 任务字段规则

| 字段 | 创建及编辑规则 |
| --- | --- |
| `group` | 新增必填，三个中文枚举值之一 |
| `name` | 非观看任务必填且非空，最长 200 字符；观看任务由服务端覆盖生成 |
| `sec` | 观看任务必填，1～86,400 的整数；非观看任务服务端忽略并移除 |
| `reward` | 新增必填，0～1,000,000 的整数 |
| `reset` | 新增必填，`一次性` 或 `每日`，不要翻译为英文枚举 |
| `en` | 新增必填，JSON 布尔值 |

上述必填约束在 PATCH 中针对合并后的整条任务校验，不要求 PATCH 重复发送未修改字段。观看任务名称规则为 `Watch dramas for ${Math.round(sec / 6) / 10} min`，以响应中的名称为准。

交互建议：新手任务默认一次性，其余默认每日；日常任务表单不显示秒数字段。观看任务名称显示为自动生成，切换为非观看任务时允许手动输入名称。

任务写接口返回的是未筛选的完整 `draft`。若列表有 `q/group`，成功后应保留筛选条件并重新 GET，或在完整响应上重新应用筛选，避免列表意外显示全部。禁止把当前筛选结果当作全量任务覆盖上传。

## 6. 实时观看奖励

读取：`GET /admin/watch-rewards`，返回 `ConfigDocument<WatchReward>`。

保存：`PUT /admin/watch-rewards`。

```json
{"revision": 1, "draft": {"sec": 5, "coins": 2, "cap": 600}}
```

| 字段 | 含义与校验 |
| --- | --- |
| `sec` | 每满多少有效播放秒数触发一次，1～86,400 的整数 |
| `coins` | 每次奖励 Bonus，1～1,000,000 的整数 |
| `cap` | 每账号每日实时奖励上限，整数，不小于 `coins`，不大于 1,000,000 |

必须提交全部三字段。默认值如示例。保存成功立即同步 `draft` 与 `published`，版本加 1，更新发布时间；此页面没有单独的发布接口或发布按钮。

说明文案：暂停、退出、切后台不计有效播放；每日上限只限实时奖励，不包含累计观看任务奖励，两类奖励独立计算。本接口只配置规则，不对用户发币。

## 7. 版本与幂等处理

所有写操作都使用当前服务端文档的 `revision`。写请求进行中禁用重复提交；同一资源的写请求应串行执行。成功后用响应的新版本替换本地版本，不要自行假定版本加一，也不要跨页面共用一个版本变量。

HTTP 409、业务码 1003 表示版本过期。提示刷新，重新读取配置；如果有未保存编辑，先保留副本或让用户确认丢弃。不要自动改版本后覆盖他人修改。

发布时使用 `crypto.randomUUID()` 生成 `Idempotency-Key`，1～200 字节。同一次发布超时后的重试复用同一个 key、同一 URL 和同一请求体。新的发布操作使用新 key；同 key 换请求内容会返回 HTTP 409、业务码 409。

幂等重试可能返回原发布操作缓存的历史文档，不一定是此刻最新文档；网络恢复或重试成功后可以再 GET 获取最新状态。普通保存、新增和删除不具备该发布幂等机制；超时后先 GET 核实，不要无条件重发新增请求。

## 8. 权限与错误响应

权限码：`tasks.view` 控制读取，`tasks.edit` 控制保存、新增、修改、删除，`tasks.publish` 控制签到及任务发布。超级管理员直接通过后端权限校验。使用登录资料中的 `is_super_admin` 与权限码列表判断，不要仅硬编码某个角色名。后端还支持 `tasks.*` 通配授权。

只读账号不显示或禁用写按钮；拥有编辑权不等于拥有发布权；发布权也不自动授予编辑权。无读取权限时不发起配置加载请求。后端最终仍会校验权限。

典型校验响应：

```json
{
  "code": 422,
  "message": "请求参数校验失败",
  "data": {"errors": {"draft.sec": ["字段校验错误信息"]}}
}
```

校验字段可能为 `draft.0.reward`、`draft.cap`、`sec`、`name`、`id`、`day` 等。嵌套校验器有时返回未带 `task.` 前缀的键；表单映射要兼容，并提供通用错误提示兜底。

| HTTP | 常见业务 code | 前端处理 |
| --- | --- | --- |
| 401 | 401、2004、2007 | 缺少令牌、令牌失效或权限版本变化，按现有登录失效流程处理 |
| 403 | 403、2006 | 权限不足或账号禁用，展示 message，不循环重试；权限不足时 data.required 返回所需权限码 |
| 404 | 404 | 任务不存在，提示并刷新列表 |
| 409 | 1003 | 草稿版本过期，重新读取并处理未保存修改 |
| 409 | 409 | 幂等键与请求内容冲突 |
| 422 | 422 | 展示 `data.errors`；没有 errors 时直接展示 message，例如缺少幂等键 |
| 500 | 5000 | 通用异常提示，允许用户重新读取状态 |

成功以 `code === 200` 判断；不要假设业务码总等于 HTTP 状态码。使用当前封装的 `ApiError` 获取 HTTP、业务码和 errors。现有其他接口可能仍使用旧响应格式，扩展请求封装时要保持兼容。

## 9. 验收清单

- 三个页面刷新后从真实接口恢复，禁用 mock 回退，加载失败明确提示。
- 签到固定七天，只能编辑奖励；保存和发布有独立状态反馈。
- 任务支持搜索、分组、新增、编辑、启停、删除；正确处理完整响应与筛选列表。
- 观看任务名称自动生成，切换分组后正确显示或移除秒数字段。
- 实时观看保存即生效，正确校验非整数、零秒数、cap 小于 coins 等输入。
- 发布按钮正确传最新版本与幂等键，超时重试不重复发布。
- 版本冲突不静默覆盖；401/403/404/422/500 有清晰反馈。
- 只读、编辑、发布权限分别验证；列表不按本地硬编码角色授权。
- 不硬编码已有任务 ID，不把接口数据写进 `operation_configs`。
- 运行前端相关测试以及 `npm run build:check`，验证三个动态菜单可打开页面。

后端本地已完成新迁移及种子初始化。其他环境拉代码后，需要先执行 `php artisan migrate --no-interaction`（生产按部署流程加 `--force`），再执行 `php artisan db:seed --class=IncentiveSeeder --no-interaction`。如果环境仍有旧 RBAC 表冲突，应由后端处理，前端不要通过改 URL 或造数据绕过。
