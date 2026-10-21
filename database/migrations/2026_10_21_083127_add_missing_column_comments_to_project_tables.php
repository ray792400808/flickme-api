<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const COMMON = [
        'id' => '主键ID', 'pk' => '自增主键ID',
        'created_at' => '创建时间（Unix秒级时间戳）',
        'updated_at' => '更新时间（Unix秒级时间戳）',
        'deleted_at' => '软删除时间（Unix秒级时间戳），NULL表示未删除',
        'published_at' => '最近发布时间（Unix秒级时间戳）',
        'name' => '名称', 'email' => '邮箱地址', 'password' => '密码哈希',
        'remember_token' => '记住登录状态的令牌',
        'user_id' => '业务用户ID，关联users.id', 'uid' => '业务用户唯一标识',
        'admin_user_id' => '后台用户ID，关联admin_users.id',
        'admin_role_id' => '后台角色ID，关联admin_roles.id',
        'admin_permission_id' => '后台权限ID，关联admin_permissions.id',
        'admin_menu_id' => '后台菜单ID，关联admin_menus.id',
        'permission_id' => '访问权限ID，关联admin_permissions.id',
        'sort' => '排序值，数值越小越靠前', 'title' => '标题',
        'description' => '描述说明', 'cover_url' => '封面图片URL',
        'tag_id' => '标签ID，关联tags.id', 'drama_id' => '短剧ID，关联dramas.id',
        'story_character_id' => '故事角色ID，关联story_characters.id',
        'currency' => '币种：member_coins会员金币，bonus奖励金币',
        'amount' => '金币变动数量，正数增加、负数扣减',
        'version' => '乐观锁版本号，用于防止并发覆盖',
        'revision' => '当前草稿版本号，保存配置后递增',
        'published_revision' => '最近发布的版本号，NULL表示尚未发布',
        'draft' => '当前草稿配置JSON', 'published' => '已发布配置快照JSON，NULL表示尚未发布',
        'queue' => '队列名称', 'payload' => '序列化的数据载荷',
        'expiration' => '过期时间（Unix秒级时间戳）',
    ];

    /** @var array<string, array<string, string>> */
    private const TABLES = [
        'admin_users' => ['login_at' => '最后登录时间（Unix秒级时间戳）'],
        'admin_roles' => [],
        'admin_permissions' => ['parent_id' => '父级权限ID，关联admin_permissions.id'],
        'admin_menus' => ['parent_id' => '父级菜单ID，关联admin_menus.id'],
        'admin_role_user' => [], 'admin_role_permission' => [], 'admin_role_menu' => [],
        'admin_user_permission' => [], 'admin_operation_logs' => [],
        'admin_audit_logs' => [
            'actor_id' => '操作人后台用户ID', 'actor' => '操作人邮箱',
            'action' => '业务审计动作标识', 'subject' => '被操作的业务资源标识',
            'details' => '业务变更明细JSON，包含修改前后内容及版本等信息',
        ],
        'admin_idempotency_keys' => [
            'actor_id' => '发起请求的后台用户ID', 'scope' => '幂等作用域，区分业务操作与资源',
            'key_hash' => 'Idempotency-Key请求头的SHA256摘要',
            'body_hash' => '请求参数JSON的SHA256摘要，防止同键用于不同请求',
            'result' => '首次成功请求的响应数据JSON，用于重试回放',
        ],
        'incentive_configs' => [
            'resource' => '激励配置类型：checkin签到奖励，tasks任务配置，watch实时观看奖励',
        ],
        'operation_configs' => [
            'resource' => '运营配置类型：chips首页分类，hot热门池，launch启动弹窗，words搜索推荐词，filters筛选标签，recs推荐位',
            'channel' => '首页分类频道：short短剧，stories故事；其他资源为空字符串',
        ],
        'users' => [
            'name' => '用户昵称', 'login_provider' => '用户登录来源',
            'member_coins' => '会员金币余额', 'bonus' => '奖励金币余额',
            'membership' => '会员类型：none无会员，weekly周会员，monthly月会员，yearly年会员',
            'unlocked_episodes' => '已解锁剧集数量', 'status' => '用户状态：active正常，banned封禁',
            'ban_reason' => '封禁原因，正常状态为NULL',
            'email_verified_at' => '邮箱验证时间（Unix秒级时间戳）',
        ],
        'coin_adjustments' => [
            'reason' => '金币调整申请原因', 'status' => '审核状态：pending待审核，approved通过，rejected拒绝',
            'requester_id' => '申请人后台用户ID', 'requester_name' => '申请人姓名快照',
            'reviewer_id' => '审核人后台用户ID', 'reviewer_name' => '审核人姓名快照',
            'review_reason' => '审核说明或拒绝原因',
        ],
        'wallet_entries' => [
            'adjustment_id' => '金币调整申请ID，关联coin_adjustments.id',
            'balance_after' => '本次变动后对应币种余额', 'expires_at' => '奖励金币过期时间（Unix秒级时间戳），NULL表示不设置',
        ],
        'tags' => ['key' => '标签唯一业务标识', 'name' => '标签名称', 'status' => '标签状态：active启用，disabled停用'],
        'dramas' => [
            'title' => '短剧标题', 'synopsis' => '短剧简介', 'status' => '内容状态：draft草稿，published已发布，unpublished已下架',
            'video_url' => '播放视频URL', 'is_playable' => '是否可播放：1是，0否', 'heat' => '短剧热度值',
        ],
        'story_characters' => [
            'name' => '故事角色名称', 'description' => '故事角色介绍',
            'status' => '内容状态：draft草稿，published已发布，unpublished已下架',
        ],
        'drama_tag' => [], 'story_character_tag' => [],
        'cache' => ['key' => '缓存唯一键', 'value' => '序列化的缓存值'],
        'cache_locks' => ['key' => '缓存锁唯一键', 'owner' => '锁持有者标识'],
        'sessions' => [
            'id' => '会话唯一业务标识', 'ip_address' => '会话客户端IP地址',
            'user_agent' => '会话客户端User-Agent', 'last_activity' => '最后活动时间（Unix秒级时间戳）',
        ],
        'password_reset_tokens' => ['token' => '密码重置令牌哈希'],
        'jobs' => [
            'attempts' => '任务执行尝试次数', 'reserved_at' => '任务被领取时间（Unix秒级时间戳）',
            'available_at' => '任务可执行时间（Unix秒级时间戳）',
        ],
        'job_batches' => [
            'id' => '任务批次唯一业务标识', 'name' => '任务批次名称',
            'total_jobs' => '批次任务总数', 'pending_jobs' => '尚未完成的任务数',
            'failed_jobs' => '失败任务数', 'failed_job_ids' => '失败任务ID列表',
            'options' => '序列化的批次配置', 'cancelled_at' => '批次取消时间（Unix秒级时间戳）',
            'finished_at' => '批次完成时间（Unix秒级时间戳）',
        ],
        'failed_jobs' => [
            'uuid' => '失败任务唯一标识', 'connection' => '队列连接名称',
            'exception' => '任务失败的异常详情', 'failed_at' => '任务失败时间（Unix秒级时间戳）',
        ],
        'migrations' => ['migration' => '已执行的迁移文件名称', 'batch' => '迁移执行批次号'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::TABLES as $table => $overrides) {
            $columns = DB::select('SELECT COLUMN_NAME, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [DB::getDatabaseName(), $table]);
            if ($columns === []) {
                continue;
            }
            $create = (array) DB::select('SHOW CREATE TABLE `'.$table.'`')[0];
            $definitions = explode("\n", $create['Create Table']);
            $changes = [];
            foreach ($columns as $column) {
                if ($column->COLUMN_COMMENT !== '') {
                    continue;
                }
                $name = $column->COLUMN_NAME;
                $comment = $overrides[$name] ?? self::COMMON[$name] ?? null;
                if ($comment === null) {
                    throw new RuntimeException('缺少字段注释定义：'.$table.'.'.$name);
                }
                foreach ($definitions as $definition) {
                    if (str_starts_with(ltrim($definition), '`'.$name.'` ')) {
                        $changes[] = 'MODIFY COLUMN '.rtrim(trim($definition), ',').' COMMENT '.DB::getPdo()->quote($comment);

                        continue 2;
                    }
                }
                throw new RuntimeException('无法读取字段原始定义：'.$table.'.'.$name);
            }
            if ($changes !== []) {
                DB::statement('ALTER TABLE `'.$table.'` '.implode(', ', $changes));
            }
        }
    }

    /** 回滚时保留说明性注释，不删除可能已被其他迁移或人工维护的字段注释。 */
    public function down(): void {}
};
