<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;

class SystemController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json(['data' => [
            'status' => 'ok',
            'service' => 'flickme-admin-api',
        ]]);
    }

    public function info(): JsonResponse
    {
        return response()->json(['data' => [
            'application' => config('app.name'),
            'environment' => app()->environment(),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => Application::VERSION,
            'timezone' => config('app.timezone'),
            'serverTime' => now()->toIso8601String(),
        ]]);
    }
}
