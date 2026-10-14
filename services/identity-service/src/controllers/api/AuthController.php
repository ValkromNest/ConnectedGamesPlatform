<?php

declare(strict_types=1);

namespace identity\controllers\api;

use identity\models\IdentityAccessToken;
use identity\models\IdentityLoginAttempt;
use identity\models\IdentityUser;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;

final class AuthController extends Controller
{
    public function beforeAction($action): bool
    {
        $this->enableCsrfValidation = false;
        Yii::$app->response->headers->set('Cache-Control', 'no-store');
        Yii::$app->response->headers->set('Pragma', 'no-cache');
        return parent::beforeAction($action);
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'login' => ['POST'],
                    'me' => ['GET'],
                    'logout' => ['POST'],
                ],
            ],
        ];
    }

    public function actionLogin(): array
    {
        $payload = Yii::$app->request->bodyParams;
        $username = is_string($payload['username'] ?? null) ? trim($payload['username']) : '';
        $password = is_string($payload['password'] ?? null) ? $payload['password'] : '';
        if ($username === '' || $password === '') {
            return $this->error(400, 'invalid_request', 'Username and password are required.');
        }

        $sourceHash = hash('sha256', Yii::$app->request->userIP ?: 'unknown');
        $attempt = IdentityLoginAttempt::find()->where(['source_hash' => $sourceHash])->one();
        if ($attempt !== null && $attempt->locked_until !== null && self::utcTimestamp((string) $attempt->locked_until) > time()) {
            return $this->error(429, 'too_many_attempts', 'Too many login attempts. Try again later.');
        }

        $user = IdentityUser::find()->where(['username' => $username])->one();
        $now = time();
        if ($user === null || !(bool) $user->is_enabled
            || ($user->locked_until !== null && self::utcTimestamp((string) $user->locked_until) > $now)
            || !Yii::$app->security->validatePassword($password, (string) $user->password_hash)
        ) {
            if ($user !== null && (bool) $user->is_enabled
                && ($user->locked_until === null || self::utcTimestamp((string) $user->locked_until) <= $now)
            ) {
                $user->failed_login_attempts = (int) $user->failed_login_attempts + 1;
                if ((int) $user->failed_login_attempts >= 8) {
                    $user->locked_until = gmdate('Y-m-d H:i:s', $now + 900);
                    $user->failed_login_attempts = 0;
                }
                $user->save(false, ['failed_login_attempts', 'locked_until']);
            }
            $this->recordFailedLogin($sourceHash);
            Yii::$app->response->statusCode = 401;
            return ['error' => ['code' => 'invalid_credentials', 'message' => 'Username or password is incorrect.']];
        }

        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->last_login_at = gmdate('Y-m-d H:i:s');
        $user->save(false, ['failed_login_attempts', 'locked_until', 'last_login_at']);
        if ($attempt !== null) {
            $attempt->delete();
        }

        $token = self::base64UrlEncode(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', $now + 8 * 60 * 60);
        $tokenRecord = new IdentityAccessToken([
            'identity_user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => gmdate('Y-m-d H:i:s', $now),
            'expires_at' => $expiresAt,
        ]);
        if (!$tokenRecord->save()) {
            Yii::error(['errors' => $tokenRecord->errors], __METHOD__);
            return $this->error(500, 'token_issue_failed', 'Could not issue an access token.');
        }

        Yii::$app->response->statusCode = 200;
        return [
            'accessToken' => $token,
            'tokenType' => 'Bearer',
            'expiresAt' => gmdate(DATE_RFC3339, $now + 8 * 60 * 60),
            'user' => $this->userPayload($user),
        ];
    }

    public function actionMe(): array
    {
        $authenticated = $this->authenticatedToken();
        if ($authenticated === null) {
            return $this->error(401, 'unauthorized', 'A valid bearer token is required.');
        }

        return ['user' => $this->userPayload($authenticated['user'])];
    }

    public function actionLogout(): array
    {
        $authenticated = $this->authenticatedToken();
        if ($authenticated === null) {
            return $this->error(401, 'unauthorized', 'A valid bearer token is required.');
        }

        $authenticated['token']->revoked_at = gmdate('Y-m-d H:i:s');
        if (!$authenticated['token']->save(false, ['revoked_at'])) {
            return $this->error(500, 'logout_failed', 'Could not revoke the access token.');
        }

        return ['success' => true];
    }

    /** @return array{token: IdentityAccessToken, user: IdentityUser}|null */
    private function authenticatedToken(): ?array
    {
        $authorization = Yii::$app->request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+([A-Za-z0-9_-]{40,})$/i', trim($authorization), $matches)) {
            return null;
        }
        $token = IdentityAccessToken::find()
            ->where([
                'token_hash' => hash('sha256', $matches[1]),
                'revoked_at' => null,
            ])
            ->andWhere(['>', 'expires_at', gmdate('Y-m-d H:i:s')])
            ->one();
        if ($token === null) {
            return null;
        }
        $user = IdentityUser::find()
            ->where(['id' => $token->identity_user_id, 'is_enabled' => true])
            ->one();
        return $user === null ? null : ['token' => $token, 'user' => $user];
    }

    /** @return array{sub: string, username: string, role: string} */
    private function userPayload(IdentityUser $user): array
    {
        return [
            'sub' => (string) $user->subject_id,
            'username' => (string) $user->username,
            'role' => (string) $user->role,
        ];
    }

    /** @return array{error: array{code: string, message: string}} */
    private function error(int $status, string $code, string $message): array
    {
        Yii::$app->response->statusCode = $status;
        return ['error' => ['code' => $code, 'message' => $message]];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function utcTimestamp(string $value): int
    {
        return strtotime($value . ' UTC') ?: 0;
    }

    private function recordFailedLogin(string $sourceHash): void
    {
        $now = time();
        $attempt = IdentityLoginAttempt::find()->where(['source_hash' => $sourceHash])->one();
        if ($attempt === null) {
            $attempt = new IdentityLoginAttempt([
                'source_hash' => $sourceHash,
                'window_started_at' => gmdate('Y-m-d H:i:s', $now),
                'failed_attempts' => 0,
            ]);
        } elseif (self::utcTimestamp((string) $attempt->window_started_at) < $now - 900) {
            $attempt->window_started_at = gmdate('Y-m-d H:i:s', $now);
            $attempt->failed_attempts = 0;
            $attempt->locked_until = null;
        }
        $attempt->failed_attempts = (int) $attempt->failed_attempts + 1;
        if ((int) $attempt->failed_attempts >= 20) {
            $attempt->locked_until = gmdate('Y-m-d H:i:s', $now + 300);
            $attempt->failed_attempts = 0;
        }
        $attempt->save();
    }
}
