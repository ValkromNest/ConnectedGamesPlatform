<?php

declare(strict_types=1);

namespace app\services;

use RuntimeException;

final class DartsGameServiceClient
{
    public function initialize(int $matchId, array $players, string $idempotencyKey): array
    {
        return $this->request(
            'PUT',
            '/api/v1/games/darts/matches/' . rawurlencode((string) $matchId) . '/context',
            [
                'contextVersion' => 1,
                'gameCode' => 'darts',
                'players' => $players,
                'configuration' => ['startingScore' => 501, 'checkout' => 'double'],
            ],
            $this->matchesToken(),
            $idempotencyKey,
        );
    }

    public function state(int $matchId): array
    {
        return $this->request(
            'GET',
            '/api/v1/games/darts/matches/' . rawurlencode((string) $matchId) . '/state',
            null,
            $this->deviceToken(),
        );
    }

    public function finish(int $matchId): array
    {
        return $this->request(
            'POST',
            '/api/v1/games/darts/matches/' . rawurlencode((string) $matchId) . '/finish',
            null,
            $this->matchesToken(),
            self::uuid(),
        );
    }

    public function recordThrow(
        int $matchId,
        int $deviceId,
        int $sector,
        int $multiplier,
        string $idempotencyKey,
    ): array {
        return $this->request(
            'POST',
            '/api/v1/games/darts/matches/' . rawurlencode((string) $matchId) . '/throws',
            [
                'deviceId' => (string) $deviceId,
                'sector' => $sector,
                'multiplier' => $multiplier,
            ],
            $this->deviceToken(),
            $idempotencyKey,
        );
    }

    private function matchesToken(): string
    {
        return $this->requiredToken('DARTS_GAME_MATCHES_TOKEN');
    }

    private function deviceToken(): string
    {
        return $this->requiredToken('DARTS_GAME_DEVICE_TOKEN');
    }

    private function requiredToken(string $name): string
    {
        $token = getenv($name);
        if (!is_string($token) || $token === '') {
            throw new RuntimeException("Missing environment credential {$name}.");
        }
        return $token;
    }

    private function request(
        string $method,
        string $path,
        ?array $payload,
        string $token,
        ?string $idempotencyKey = null,
    ): array {
        $baseUrl = rtrim(getenv('DARTS_GAME_SERVICE_URL') ?: 'http://127.0.0.1:8003', '/');
        $correlationId = self::uuid();
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'X-Correlation-ID: ' . $correlationId,
        ];
        $content = '';
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $content = json_encode($payload, JSON_THROW_ON_ERROR);
        }
        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $content,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($baseUrl . $path, false, $context);
        if ($response === false) {
            throw new DartsGameServiceException('Darts game service is unavailable.', 503);
        }
        $decoded = json_decode($response, true);
        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('/\s([0-9]{3})\s/', $statusLine, $matches)) {
            throw new DartsGameServiceException('Darts game service returned an invalid HTTP response.', 502);
        }
        $statusCode = (int) $matches[1];
        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($decoded)
                ? ($decoded['error']['message'] ?? $decoded['message'] ?? null)
                : null;
            $message = is_string($message) && $message !== ''
                ? $message
                : 'HTTP ' . $statusCode . ' from Darts game service.';
            throw new DartsGameServiceException($message, $statusCode);
        }
        if (!is_array($decoded)) {
            throw new DartsGameServiceException('Darts game service returned invalid JSON.', 502);
        }
        return $decoded;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
