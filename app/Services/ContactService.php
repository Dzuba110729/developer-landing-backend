<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\OwnerContactNotification;
use App\Mail\UserContactConfirmation;
use App\Repositories\ContactRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactService
{
    public function __construct(
        private readonly AiService $aiService,
        private readonly ContactRepository $contactRepository,
    ) {}

    /**
     * Полный цикл обработки заявки: AI-анализ комментария → отправка писем → сохранение в файловый журнал.
     * Отправка писем не должна "ронять" запрос — при сбое SMTP заявка всё равно фиксируется.
     */
    public function handle(array $data): array
    {
        $ai = $this->aiService->analyzeComment($data['comment']);

        $mailSent = $this->sendNotifications($data, $ai);

        $entry = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'comment' => $data['comment'],
            'ai_used' => $ai['used'],
            'ai_sentiment' => $ai['sentiment'],
            'ai_category' => $ai['category'],
            'mail_sent' => $mailSent,
        ];

        $this->contactRepository->save($entry);

        return [
            'ai' => $ai,
            'mail_sent' => $mailSent,
        ];
    }

    private function sendNotifications(array $data, array $ai): bool
    {
        $ownerEmail = config('mail.contact_owner_email');

        try {
            Mail::to($ownerEmail)->send(new OwnerContactNotification($data, $ai));
            Mail::to($data['email'])->send(new UserContactConfirmation($data, $ai));

            return true;
        } catch (Throwable $e) {
            Log::error('ContactService: не удалось отправить email-уведомления', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
