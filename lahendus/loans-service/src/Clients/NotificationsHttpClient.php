<?php

declare(strict_types=1);

namespace Laenutus\Clients;

use Laenutus\Config;
use Laenutus\HttpClient;

final class NotificationsHttpClient
{
    /** @return array<string, mixed> */
    public function create(array $payload, ?string $requestId): array
    {
        $client = new HttpClient(
            Config::get('NOTIFICATIONS_SERVICE_URL', 'http://notifications-service'),
            null,
            $requestId,
            $this->internalHeaders(),
        );

        return $client->postJson('/notifications', $payload);
    }

    /** @return array<string, string> */
    private function internalHeaders(): array
    {
        $key = Config::get('INTERNAL_API_KEY');
        if ($key === null || $key === '') {
            return [];
        }

        return ['X-Internal-Api-Key' => $key];
    }
}
