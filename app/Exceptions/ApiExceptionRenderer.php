<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Глобальная обработка ошибок API: единый JSON-конверт {success, message, ...}
 * с корректными HTTP-статусами вместо HTML-страниц Laravel по умолчанию.
 */
class ApiExceptionRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (ValidationException $e, Request $request) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ошибка валидации данных.',
                'errors' => $e->errors(),
            ], 422);
        });

        // FormRequest::failedValidation() и кастомный rate-limit-колбэк бросают
        // HttpResponseException с уже готовым JSON-ответом внутри — просто отдаём его.
        $exceptions->render(function (HttpResponseException $e, Request $request) {
            return $e->getResponse();
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Требуется аутентификация.',
            ], 401);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Запрашиваемый ресурс не найден.',
            ], 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Метод не поддерживается для данного эндпоинта.',
            ], 405);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Слишком много запросов. Попробуйте позже.',
            ], 429, $e->getHeaders());
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage() ?: 'Ошибка обработки запроса.',
            ], $e->getStatusCode());
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            return new JsonResponse([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Внутренняя ошибка сервера.',
            ], 500);
        });
    }
}
