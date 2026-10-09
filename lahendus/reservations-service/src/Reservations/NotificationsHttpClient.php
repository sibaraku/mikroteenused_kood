<?php

declare(strict_types=1);

namespace Laenutus\Reservations;

use Laenutus\Config;
use Laenutus\HttpClient;

final class NotificationsHttpClient
{
    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function create(array $payload, ?string $requestId): array
    {
        $key = Config::get('INTERNAL_API_KEY');
        $client = new HttpClient(
            Config::get('NOTIFICATIONS_SERVICE_URL', 'http://notifications-service'),
            null,
            $requestId,
            $key !== null && $key !== '' ? ['X-Internal-Api-Key' => $key] : [],
        );

        return $client->postJson('/notifications', $payload);
    }
}