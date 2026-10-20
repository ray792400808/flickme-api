<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AppController extends Controller
{
    private const LOCALES = ['en', 'zh-Hans', 'zh-Hant', 'es', 'pt', 'id', 'th'];

    public function health(): JsonResponse
    {
        return response()->json(['data' => [
            'status' => 'ok',
            'service' => 'flickme-api',
        ]]);
    }

    public function config(): JsonResponse
    {
        return response()->json(['data' => [
            'name' => 'Flickme',
            'locales' => self::LOCALES,
            'features' => [
                'chat' => false,
                'imageGen' => false,
                'videoGen' => false,
                'ads' => false,
                'iap' => false,
                'comments' => false,
                'search' => false,
            ],
        ]]);
    }

    public function locales(): JsonResponse
    {
        return response()->json(['data' => [
            'default' => 'en',
            'supported' => self::LOCALES,
        ]]);
    }

    public function serverTime(): JsonResponse
    {
        $now = now();

        return response()->json(['data' => [
            'iso8601' => $now->toIso8601String(),
            'timestampMs' => $now->getTimestampMs(),
            'timezone' => config('app.timezone'),
        ]]);
    }
}
