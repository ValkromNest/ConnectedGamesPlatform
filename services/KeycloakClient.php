<?php

declare(strict_types=1);
namespace app\services;

use RuntimeException;

/** OAuth2 authorization code client. Tokens never leave the PHP session. */
final class KeycloakClient
{
    public function issuer(): string
    {
        return rtrim($this->setting('KEYCLOAK_ISSUER'), '/');
    }

    public function setting(string $name): string
    {
        $value = getenv($name);
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('Missing configuration: ' . $name);
        }
        return $value;
    }

    public function endpoint(string $name): string
    {
        return $this->issuer() . '/protocol/openid-connect/' . $name;
    }

    public function token(array $parameters): array
    {
        return $this->post('token', $parameters);
    }

    public function post(string $endpoint, array $parameters): array
    {
        $parameters['client_id'] = $this->setting('KEYCLOAK_CLIENT_ID');
        $parameters['client_secret'] = $this->setting('KEYCLOAK_CLIENT_SECRET');
        $context = stream_context_create(['http' => [
            'method' => 'POST', 'timeout' => 5, 'ignore_errors' => true,
            'follow_location' => 0,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json",
            'content' => http_build_query($parameters),
        ]]);
        $body = @file_get_contents($this->endpoint($endpoint), false, $context);
        $status = $http_response_header[0] ?? '';
        if ($body === false || !preg_match('/\s200\s/', $status)) {
            throw new RuntimeException('Keycloak request failed.');
        }
        $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($result)) {
            throw new RuntimeException('Invalid Keycloak response.');
        }
        return $result;
    }

    public static function accessToken(): string
    {
        $session = \Yii::$app->session;
        if ((int) $session->get('keycloak_expires_at', 0) <= time() + 30) {
            $refresh = $session->get('keycloak_refresh_token');
            if (!is_string($refresh) || $refresh === '') {
                throw new RuntimeException('Login required.');
            }
            self::store((new self())->token(['grant_type' => 'refresh_token', 'refresh_token' => $refresh]));
        }
        $token = $session->get('identity_service_access_token');
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Login required.');
        }
        return $token;
    }

    public static function store(array $tokens): void
    {
        if (!is_string($tokens['access_token'] ?? null) || (int) ($tokens['expires_in'] ?? 0) <= 0
            || !is_string($tokens['refresh_token'] ?? null)) {
            throw new RuntimeException('Invalid token response.');
        }
        $session = \Yii::$app->session;
        $session->set('identity_service_access_token', $tokens['access_token']);
        $session->set('keycloak_refresh_token', $tokens['refresh_token']);
        $session->set('keycloak_expires_at', time() + (int) $tokens['expires_in']);
    }
}
