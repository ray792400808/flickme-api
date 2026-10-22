<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminOperationLog;
use App\Services\Admin\AdminOperationLogService;
use Illuminate\Http\Request;

class AdminOperationLogController extends Controller
{
    public function __construct(private readonly AdminOperationLogService $logService) {}

    public function index(Request $request)
    {
        $logs = $this->logService->list($request->all());

        return $this->success($logs);
    }

    public function show(AdminOperationLog $log)
    {
        return $this->success([
            'id' => $log->id,
            'username' => $log->username,
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ] : null,
            'method' => $log->method,
            'path' => $log->path,
            'ip' => $log->ip,
            'input' => $log->input,
            'description' => $log->description,
            'created_at' => $log->created_at->getTimestamp(),
        ]);
    }

    public function destroy(AdminOperationLog $log)
    {
        $this->logService->delete($log);

        return $this->success(['deleted' => true], '删除成功');
    }

    public function purge(Request $request)
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $count = $this->logService->purge($data['days']);

        return $this->success(['purged' => $count], "已清理 {$count} 条日志");
    }
}
