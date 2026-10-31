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

    public function actionMe(): array
    {
        $authenticated = $this->authenticatedToken();
        if ($authenticated === null) {
            return $this->error(401, 'unauthorized', 'A valid bearer token is required.');
        }

        return ['user' => $this->userPayload($authenticated['user'])];
    }

    /** Elenco minimo dei player abilitati, accessibile solo agli admin. */
    public function actionPlayers(): array
    {
        $authenticated = $this->authenticatedToken();
        if ($authenticated === null) {
            return $this->error(401, 'unauthorized', 'A valid bearer token is required.');
        }
        if ($authenticated['user']->role !== 'admin') {
            return $this->error(403, 'forbidden', 'Administrator role is required.');
        }

        $players = IdentityUser::find()
            ->select(['subject_id', 'username', 'role'])
            ->where(['role' => 'player', 'is_enabled' => 1])
            ->orderBy(['username' => SORT_ASC])
            ->asArray()
            ->all();

        return ['players' => array_map(static fn (array $player): array => [
            'sub' => (string) $player['subject_id'],
            'username' => (string) $player['username'],
            'role' => (string) $player['role'],
        ], $players)];
    }

    /** @return array{user: IdentityUser}|null */
    private function authenticatedToken(): ?array
    {
        $authorization = Yii::$app->request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer ([A-Za-z0-9._~-]+)$/', $authorization, $matches)) {
            return null;
        }
        require_once dirname(__DIR__, 4) . '/KeycloakClient.php';
        try {
            $client = new \app\services\KeycloakClient();
            $claims = $client->inspect($matches[1]);
        } catch (\Throwable $exception) {
            Yii::warning('Access token verification failed.', __METHOD__);
            return null;
        }
        $user = IdentityUser::find()->where([
            'keycloak_subject' => $claims['sub'], 'keycloak_issuer' => $client->issuer(), 'is_enabled' => 1,
        ])->one();
        return $user === null ? null : ['user' => $user];
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

}
