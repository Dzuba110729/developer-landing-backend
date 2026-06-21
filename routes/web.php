<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => config('app.name'),
        'description' => 'Backend для лендинга разработчика. См. /api/docs для документации API.',
        'endpoints' => [
            'health' => '/api/health',
            'metrics' => '/api/metrics',
            'contact' => '/api/contact',
            'docs' => '/api/docs',
        ],
    ]);
});

Route::get('/api/docs', function () {
    return view('swagger');
});

Route::get('/api/openapi.yaml', function () {
    return response()->file(base_path('openapi.yaml'), ['Content-Type' => 'text/yaml']);
});
