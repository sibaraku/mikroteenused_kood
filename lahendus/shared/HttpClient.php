<?php

declare(strict_types=1);

namespace Laenutus;

use Laenutus\Auth\AuthException;

final class HttpClient
{
    /** @param array<string, string> $extraHeaders */
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $bearerToken = null,
        private readonly ?string $requestId = null,
        private readonly array $extraHeaders = [],
    ) {}

    /** @return array{status: int, body: array<string, mixed>|null} */
    public function request(string $method, string $path, ?array $body = null, array $query = []): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->bearerToken !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->bearerToken;
        }

        if ($this->requestId !== null) {
            $headers[] = 'X-Request-Id: ' . $this->requestId;
        }

        foreach ($this->extraHeaders as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new AuthException('SERVICE_UNAVAILABLE', 'HTTP klienti ei saanud käivitada', 502);
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $options);

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new AuthException('SERVICE_UNAVAILABLE', 'Teenuse kutse ebaõnnestus: ' . $curlError, 502);
        }

        $decoded = json_decode($responseBody, true);

        return [
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : null,
        ];
    }

    /** @return array<string, mixed> */
    public function getJson(string $path, array $query = []): array
    {
        $result = $this->request('GET', $path, null, $query);
        $this->assertSuccess($result);

        return $result['body'] ?? [];
    }

    /** @return array<string, mixed> */
    public function postJson(string $path, array $body): array
    {
        $result = $this->request('POST', $path, $body);
        $this->assertSuccess($result);

        return $result['body'] ?? [];
    }

    /** @return array<string, mixed> */
    public function patchJson(string $path, array $body): array
    {
        $result = $this->request('PATCH', $path, $body);
        $this->assertSuccess($result);

        return $result['body'] ?? [];
    }

    public function delete(string $path): void
    {
        $result = $this->request('DELETE', $path);
        if ($result['status'] !== 204 && ($result['status'] < 200 || $result['status'] >= 300)) {
            $this->assertSuccess($result);
        }
    }

    /** @param array{status: int, body: array<string, mixed>|null} $result */
    private function assertSuccess(array $result): void
    {
        if ($result['status'] >= 200 && $result['status'] < 300) {
            return;
        }

        $body = $result['body'];
        $code = is_array($body) ? (string) ($body['code'] ?? 'SERVICE_ERROR') : 'SERVICE_ERROR';
        $message = is_array($body) ? (string) ($body['message'] ?? 'Teenuse viga') : 'Teenuse viga';

        throw new AuthException($code, $message, $result['status'] >= 400 ? $result['status'] : 502);
    }
}
