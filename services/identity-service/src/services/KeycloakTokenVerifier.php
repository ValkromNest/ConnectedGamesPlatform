<?php

declare(strict_types=1);
namespace identity\services;

use RuntimeException;

/** Verifies access tokens through authenticated Keycloak introspection. */
final class KeycloakTokenVerifier
{
    public function issuer(): string
    {
        return rtrim($this->setting('KEYCLOAK_ISSUER'), '/');
    }

    private function setting(string $name): string
    {
        $value = getenv($name);
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('Missing configuration: ' . $name);
        }
        return $value;
    }

    private function endpoint(string $name): string
    {
        return $this->issuer() . '/protocol/openid-connect/' . $name;
    }

    private function post(string $endpoint, array $parameters): array
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

    public function inspect(string $token): array
    {
        $claims = $this->post('token/introspect', ['token' => $token]);
        $audience = (array) ($claims['aud'] ?? []);
        if (($claims['active'] ?? false) !== true
            || ($claims['iss'] ?? null) !== $this->issuer()
            || !in_array($this->setting('KEYCLOAK_AUDIENCE'), $audience, true)
            || !is_string($claims['sub'] ?? null)
            || ($claims['exp'] ?? 0) <= time()
        ) {
            throw new RuntimeException('Invalid access token.');
        }
        return $claims;
    }

}
