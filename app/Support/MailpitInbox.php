<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\Factory as Http;
use Throwable;

/**
 * Reads the Mailpit inbox over its HTTP API so the development console can show
 * what actually left the application. Every failure degrades to "unavailable"
 * rather than breaking the console.
 */
final class MailpitInbox
{
    public function __construct(
        private readonly Http $http,
        private readonly string $baseUrl,
    ) {}

    /**
     * @return array{available: bool, total: int, messages: list<array{id: string, subject: string, to: string, at: string}>}
     */
    public function recent(int $limit = 12): array
    {
        try {
            $response = $this->http->acceptJson()->connectTimeout(1)->timeout(2)
                ->get($this->baseUrl.'/api/v1/messages', ['limit' => $limit]);

            if ($response->failed()) {
                return self::unavailable();
            }

            /** @var list<array<string, mixed>> $messages */
            $messages = $response->json('messages', []);

            return [
                'available' => true,
                'total' => (int) $response->json('total', 0),
                'messages' => array_map(self::message(...), $messages),
            ];
        } catch (Throwable) {
            return self::unavailable();
        }
    }

    public function clear(): bool
    {
        try {
            return $this->http->connectTimeout(1)->timeout(2)
                ->delete($this->baseUrl.'/api/v1/messages')
                ->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{id: string, subject: string, to: string, at: string}
     */
    private static function message(array $message): array
    {
        /** @var list<array{Address?: string}> $to */
        $to = is_array($message['To'] ?? null) ? $message['To'] : [];

        return [
            'id' => (string) ($message['ID'] ?? ''),
            'subject' => (string) ($message['Subject'] ?? '(no subject)'),
            'to' => (string) ($to[0]['Address'] ?? ''),
            'at' => (string) ($message['Created'] ?? ''),
        ];
    }

    /**
     * @return array{available: bool, total: int, messages: list<array{id: string, subject: string, to: string, at: string}>}
     */
    private static function unavailable(): array
    {
        return ['available' => false, 'total' => 0, 'messages' => []];
    }
}
