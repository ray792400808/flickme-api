<?php

namespace App\Services\Admin;

use App\Models\AdminOperationLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminOperationLogService
{
    // ─── 列表 ───────────────────────────────────────────────

    public function list(array $filters): LengthAwarePaginator
    {
        $query = AdminOperationLog::query()->with('user:id,name,email');

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('admin_user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['method'])) {
            $query->where('method', strtoupper(trim($filters['method'])));
        }

        if (! empty($filters['keyword'])) {
            $keyword = trim($filters['keyword']);
            $query->where(fn ($q) => $q->where('username', 'like', "%{$keyword}%")
                ->orWhere('path', 'like', "%{$keyword}%")
                ->orWhere('description', 'like', "%{$keyword}%"));
        }

        return $query->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();
    }

    // ─── 详情 ───────────────────────────────────────────────

    public function find(int $id): AdminOperationLog
    {
        return AdminOperationLog::query()->with('user:id,name,email')->findOrFail($id);
    }

    // ─── 删除 ───────────────────────────────────────────────

    public function delete(AdminOperationLog $log): void
    {
        $log->delete();
    }

    // ─── 批量清理 ─────────────────────────────────────────

    public function purge(int $days): int
    {
        return AdminOperationLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    // ─── 审计日志 ─────────────────────────────────────────

    public function audit(
        int $adminUserId,
        string $username,
        string $method,
        string $path,
        ?string $ip,
        string $action,
        string $subject,
        array $input = [],
    ): void {
        AdminOperationLog::query()->create([
            'admin_user_id' => $adminUserId,
            'username' => $username,
            'method' => $method,
            'path' => $path,
            'ip' => $ip,
            'input' => $input,
            'description' => $action.'：'.$subject,
        ]);
    }
}
