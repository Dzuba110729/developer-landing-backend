<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $contactService) {}

    public function __invoke(ContactRequest $request): JsonResponse
    {
        $result = $this->contactService->handle($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Заявка принята. Мы свяжемся с вами в ближайшее время.',
            'data' => [
                'mail_sent' => $result['mail_sent'],
                'ai' => [
                    'used' => $result['ai']['used'],
                    'sentiment' => $result['ai']['sentiment'],
                    'category' => $result['ai']['category'],
                ],
            ],
        ], 201);
    }
}
