<?php

declare(strict_types=1);

namespace Laenutus\Clients;

use Laenutus\Config;
use Laenutus\HttpClient;

final class ReservationsHttpClient
{
    private function client(?string $token, ?string $requestId): HttpClient
    {
        return new HttpClient(
            Config::get('RESERVATIONS_SERVICE_URL', 'http://reservations-service'),
            $token,
            $requestId,
        );
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $token, ?string $requestId): array
    {
        /** @var list<array<string, mixed>> */
        return $this->client($token, $requestId)->getJson('/reservations');
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    public function create(array $data, ?string $token, ?string $requestId): array
    {
        return $this->client($token, $requestId)->postJson('/reservations', $data);
    }

    public function cancel(string $id, ?string $token, ?string $requestId): void
    {
        $this->client($token, $requestId)->delete('/reservations/' . rawurlencode($id));
    }

    public function notifyNext(string $itemId, ?string $requestId): void
    {
        $key = Config::get('INTERNAL_API_KEY');
        $client = new HttpClient(
            Config::get('RESERVATIONS_SERVICE_URL', 'http://reservations-service'),
            null,
            $requestId,
            $key !== null && $key !== '' ? ['X-Internal-Api-Key' => $key] : [],
        );
        $client->postJson('/reservations/notify-next', ['itemId' => $itemId]);
    }
}