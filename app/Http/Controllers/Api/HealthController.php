<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $storageWritable = is_writable(storage_path('app/data'));

        return response()->json([
            'success' => true,
            'status' => $storageWritable ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'env' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'storage_writable' => $storageWritable,
                'ai_configured' => filled(config('services.anthropic.api_key')),
            ],
        ]);
    }
}
