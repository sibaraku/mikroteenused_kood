<?php

declare(strict_types=1);

namespace Laenutus\Clients;

use Laenutus\Config;
use Laenutus\HttpClient;

final class ItemsHttpClient
{
    private function client(?string $token, ?string $requestId): HttpClient
    {
        return new HttpClient(
            Config::get('ITEMS_SERVICE_URL', 'http://items-service'),
            $token,
            $requestId,
        );
    }

    /** @return array<string, mixed> */
    public function get(string $itemId, ?string $token, ?string $requestId): array
    {
        return $this->client($token, $requestId)->getJson('/items/' . rawurlencode($itemId));
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $status, ?string $token, ?string $requestId): array
    {
        $query = $status !== null ? ['status' => $status] : [];

        /** @var list<array<string, mixed>> */
        return $this->client($token, $requestId)->getJson('/items', $query);
    }

    /** @param array<string, mixed> $data */
    public function update(string $itemId, array $data, ?string $token, ?string $requestId): array
    {
        return $this->client($token, $requestId)->patchJson('/items/' . rawurlencode($itemId), $data);
    }

    public function reserve(string $itemId, ?string $token, ?string $requestId): void
    {
        $this->client($token, $requestId)->postJson('/items/' . rawurlencode($itemId) . '/reserve', []);
    }

    public function release(string $itemId, ?string $token, ?string $requestId): void
    {
        $this->client($token, $requestId)->postJson('/items/' . rawurlencode($itemId) . '/release', []);
    }
}
