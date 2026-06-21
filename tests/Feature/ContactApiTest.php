<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\OwnerContactNotification;
use App\Mail\UserContactConfirmation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $dataDir = storage_path('app/data');
        if (is_dir($dataDir)) {
            foreach (['contacts.jsonl', 'stats.json'] as $file) {
                @unlink($dataDir.'/'.$file);
            }
        }
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Иван Иванов',
            'phone' => '+7 999 123-45-67',
            'email' => 'ivan@example.com',
            'comment' => 'Хочу обсудить разработку лендинга для моего проекта.',
        ], $overrides);
    }

    public function test_contact_validation_fails_with_missing_fields(): void
    {
        $this->postJson('/api/contact', [])
            ->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors(['name', 'phone', 'email', 'comment']);
    }

    public function test_contact_submission_succeeds_with_ai_and_mail(): void
    {
        Mail::fake();

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => '{"sentiment":"positive","category":"cooperation","suggested_reply":"Спасибо за обращение!"}'],
                ],
            ], 200),
        ]);

        config(['services.anthropic.api_key' => 'test-key']);

        $payload = $this->validPayload();

        $response = $this->postJson('/api/contact', $payload)
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'mail_sent' => true,
                    'ai' => [
                        'used' => true,
                        'sentiment' => 'positive',
                        'category' => 'cooperation',
                    ],
                ],
            ]);

        Mail::assertSent(OwnerContactNotification::class);
        Mail::assertSent(UserContactConfirmation::class, function ($mail) use ($payload) {
            return $mail->hasTo($payload['email']);
        });

        $this->assertFileExists(storage_path('app/data/contacts.jsonl'));
        $this->assertFileExists(storage_path('app/data/stats.json'));

        $stats = json_decode(file_get_contents(storage_path('app/data/stats.json')), true);
        $this->assertSame(1, $stats['total_requests']);
        $this->assertSame(1, $stats['ai_processed']);
    }

    public function test_contact_submission_falls_back_gracefully_when_ai_unavailable(): void
    {
        Mail::fake();

        Http::fake([
            'api.anthropic.com/*' => Http::response([], 500),
        ]);

        config(['services.anthropic.api_key' => 'test-key']);

        $this->postJson('/api/contact', $this->validPayload())
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'ai' => [
                        'used' => false,
                        'sentiment' => null,
                        'category' => null,
                    ],
                ],
            ]);

        Mail::assertSent(OwnerContactNotification::class);
    }

    public function test_contact_endpoint_is_rate_limited(): void
    {
        Mail::fake();
        Http::fake();

        config(['contact.rate_limit.max_attempts' => 2]);

        $this->postJson('/api/contact', $this->validPayload())->assertStatus(201);
        $this->postJson('/api/contact', $this->validPayload())->assertStatus(201);

        $this->postJson('/api/contact', $this->validPayload())
            ->assertStatus(429)
            ->assertJson(['success' => false]);
    }
}
