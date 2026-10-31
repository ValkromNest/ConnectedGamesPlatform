<?php

declare(strict_types=1);

namespace app\models;

use app\services\IdentityServiceClient;
use Throwable;
use Yii;
use yii\web\IdentityInterface;

final class AuthenticatedUser implements IdentityInterface
{
    public readonly string $username;
    public readonly string $role;

    /** @param array{sub: string, username: string, role: string} $profile */
    public function __construct(private readonly array $profile)
    {
        $this->username = $profile['username'];
        $this->role = $profile['role'];
    }

    public static function findIdentity($id): ?static
    {
        $token = Yii::$app->session->get('identity_service_access_token');
        if (!is_string($token) || $token === '') {
            return null;
        }
        try {
            $token = \app\services\KeycloakClient::accessToken();
            $profile = (new IdentityServiceClient())->me($token);
        } catch (Throwable $exception) {
            Yii::warning('Identity validation failed: ' . $exception->getMessage(), __METHOD__);
            Yii::$app->session->remove('identity_service_access_token');
            return null;
        }
        return hash_equals((string) $id, $profile['sub']) ? new static($profile) : null;
    }

    public static function findIdentityByAccessToken($token, $type = null): ?static
    {
        return null;
    }

    public function getId(): string
    {
        return $this->profile['sub'];
    }

    public function getAuthKey(): ?string
    {
        return null;
    }

    public function validateAuthKey($authKey): bool
    {
        return false;
    }

    public function getUsername(): string
    {
        return $this->profile['username'];
    }

    public function isAdministrator(): bool
    {
        return $this->profile['role'] === 'admin';
    }
}
