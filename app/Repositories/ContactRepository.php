<?php

declare(strict_types=1);

namespace App\Repositories;

class ContactRepository
{
    private string $logPath;

    private string $statsPath;

    public function __construct()
    {
        $this->logPath = storage_path('app/data/contacts.jsonl');
        $this->statsPath = storage_path('app/data/stats.json');

        $this->ensureFile($this->logPath);
        $this->ensureFile($this->statsPath, $this->defaultStats());
    }

    /**
     * Сохранить заявку в журнал (JSON Lines, append-only) и обновить агрегированную статистику.
     */
    public function save(array $entry): void
    {
        $entry['id'] = (string) str()->uuid();
        $entry['created_at'] = now()->toIso8601String();

        $this->appendLine($this->logPath, json_encode($entry, JSON_UNESCAPED_UNICODE));
        $this->updateStats($entry);
    }

    public function getStats(): array
    {
        $handle = fopen($this->statsPath, 'r');
        if ($handle === false) {
            return $this->defaultStats();
        }

        flock($handle, LOCK_SH);
        $contents = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        $stats = json_decode((string) $contents, true);

        return is_array($stats) ? $stats : $this->defaultStats();
    }

    private function updateStats(array $entry): void
    {
        $handle = fopen($this->statsPath, 'c+');
        if ($handle === false) {
            return;
        }

        flock($handle, LOCK_EX);

        $contents = stream_get_contents($handle);
        $stats = json_decode((string) $contents, true);
        if (! is_array($stats)) {
            $stats = $this->defaultStats();
        }

        $stats['total_requests']++;
        $stats['mail_sent'] += $entry['mail_sent'] ? 1 : 0;
        $stats['mail_failed'] += $entry['mail_sent'] ? 0 : 1;
        $stats['ai_processed'] += $entry['ai_used'] ? 1 : 0;
        $stats['ai_fallback'] += $entry['ai_used'] ? 0 : 1;

        $sentiment = $entry['ai_sentiment'] ?? 'unknown';
        $stats['by_sentiment'][$sentiment] = ($stats['by_sentiment'][$sentiment] ?? 0) + 1;

        $today = now()->toDateString();
        $stats['by_date'][$today] = ($stats['by_date'][$today] ?? 0) + 1;

        $stats['last_request_at'] = now()->toIso8601String();

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($handle);

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function appendLine(string $path, string $line): void
    {
        $handle = fopen($path, 'a');
        if ($handle === false) {
            return;
        }

        flock($handle, LOCK_EX);
        fwrite($handle, $line.PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function ensureFile(string $path, ?array $initialContent = null): void
    {
        if (file_exists($path)) {
            return;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents(
            $path,
            $initialContent === null ? '' : json_encode($initialContent, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    private function defaultStats(): array
    {
        return [
            'total_requests' => 0,
            'mail_sent' => 0,
            'mail_failed' => 0,
            'ai_processed' => 0,
            'ai_fallback' => 0,
            'by_sentiment' => [],
            'by_date' => [],
            'last_request_at' => null,
        ];
    }
}
