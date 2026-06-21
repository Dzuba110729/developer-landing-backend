<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\ContactRepository;
use Illuminate\Http\JsonResponse;

class MetricsController extends Controller
{
    public function __construct(private readonly ContactRepository $contactRepository) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->contactRepository->getStats(),
        ]);
    }
}
