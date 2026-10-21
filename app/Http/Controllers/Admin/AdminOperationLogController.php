<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminOperationLog;
use App\Services\Admin\AdminOperationLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOperationLogController extends Controller
{
    public function __construct(private readonly AdminOperationLogService $logService) {}

    public function index(Request $request): JsonResponse
    {
        $logs = $this->logService->list($request->all());

        return response()->json(['data' => $logs]);
    }

    public function show(AdminOperationLog $log): JsonResponse
    {
        return response()->json(['data' => [
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
            'created_at' => $log->created_at->toIso8601String(),
        ]]);
    }

    public function destroy(AdminOperationLog $log): JsonResponse
    {
        $this->logService->delete($log);

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function purge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $count = $this->logService->purge($data['days']);

        return response()->json(['data' => ['purged' => $count]]);
    }
}
