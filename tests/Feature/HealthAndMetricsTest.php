<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class HealthAndMetricsTest extends TestCase
{
    public function test_health_endpoint_returns_ok_status(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'ok'])
            ->assertJsonStructure(['success', 'status', 'app', 'env', 'timestamp', 'checks']);
    }

    public function test_metrics_endpoint_returns_stats_structure(): void
    {
        $this->getJson('/api/metrics')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_requests',
                    'mail_sent',
                    'mail_failed',
                    'ai_processed',
                    'ai_fallback',
                    'by_sentiment',
                    'by_date',
                    'last_request_at',
                ],
            ]);
    }

    public function test_unknown_endpoint_returns_json_404(): void
    {
        $this->getJson('/api/unknown')
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }
}
