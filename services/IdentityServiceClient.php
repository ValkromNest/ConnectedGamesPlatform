<?php

declare(strict_types=1);

namespace app\services;

use RuntimeException;

final class IdentityServiceClient
{
    /** @return array{accessToken: string, tokenType: string, expiresAt: string, user: array{sub: string, username: string, role: string}} */
    public function login(string $username, string $password): array
    {
        return $this->request('POST', '/api/v1/auth/login', [
            'username' => $username,
            'password' => $password,
        ]);
    }

    /** @return array{sub: string, username: string, role: string} */
    public function me(string $accessToken): array
    {
        $response = $this->request('GET', '/api/v1/auth/me', null, $accessToken);
        $user = $response['user'] ?? null;
        if (!is_array($user) || !is_string($user['sub'] ?? null)
            || !is_string($user['username'] ?? null) || !is_string($user['role'] ?? null)
        ) {
            throw new IdentityServiceException('Identity service returned an invalid user profile.', 502);
        }
        return $user;
    }

    public function logout(string $accessToken): void
    {
        $this->request('POST', '/api/v1/auth/logout', [], $accessToken);
    }

    /** @param array<string, mixed>|null $payload
     *  @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $payload = null, ?string $accessToken = null): array
    {
        $baseUrl = rtrim(getenv('IDENTITY_SERVICE_URL') ?: 'http://127.0.0.1:8001', '/');
        $headers = ['Accept: application/json'];
        $content = '';
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $content = json_encode($payload, JSON_THROW_ON_ERROR);
        }
        if ($accessToken !== null) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $content,
            'timeout' => 4,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($baseUrl . $path, false, $context);
        if ($body === false) {
            throw new IdentityServiceException('Identity service is unavailable.', 503);
        }
        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('/\s([0-9]{3})\s/', $statusLine, $matches)) {
            throw new IdentityServiceException('Identity service returned an invalid HTTP response.', 502);
        }
        $status = (int) $matches[1];
        $decoded = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) ? ($decoded['error']['message'] ?? null) : null;
            throw new IdentityServiceException(
                is_string($message) && $message !== '' ? $message : 'Identity service returned HTTP ' . $status . '.',
                $status,
            );
        }
        if (!is_array($decoded)) {
            throw new IdentityServiceException('Identity service returned invalid JSON.', 502);
        }
        return $decoded;
    }
}
