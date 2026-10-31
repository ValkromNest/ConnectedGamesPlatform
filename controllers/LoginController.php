<?php

declare(strict_types=1);
namespace app\controllers;

use app\models\AuthenticatedUser;
use app\services\IdentityServiceClient;
use app\services\KeycloakClient;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;
use yii\web\BadRequestHttpException;

final class LoginController extends Controller
{
    public function behaviors(): array
    {
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => ['logout' => ['POST']]]];
    }

    public function actionIndex(): Response|string
    {
        if (!Yii::$app->user->isGuest) {
            return $this->redirect(['/dashboard/index']);
        }
        return $this->render('index');
    }

    public function actionAuthorize(): Response
    {
        $client = new KeycloakClient();
        $state = bin2hex(random_bytes(32));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        Yii::$app->session->set('oauth_transaction', ['state' => $state, 'verifier' => $verifier, 'time' => time()]);
        return $this->redirect($client->endpoint('auth') . '?' . http_build_query([
            'client_id' => $client->setting('KEYCLOAK_CLIENT_ID'),
            'redirect_uri' => $client->setting('KEYCLOAK_REDIRECT_URI'),
            'response_type' => 'code', 'scope' => 'openid profile', 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]));
    }

    public function actionCallback(): Response
    {
        $transaction = Yii::$app->session->get('oauth_transaction');
        Yii::$app->session->remove('oauth_transaction');
        $state = $this->request->get('state');
        $code = $this->request->get('code');
        if (!is_array($transaction) || !is_string($state)
            || !hash_equals($transaction['state'], $state) || time() - $transaction['time'] > 600) {
            throw new BadRequestHttpException('Richiesta di accesso scaduta o non valida.');
        }
        try {
            if (!is_string($code) || $code === '') {
                throw new \RuntimeException('Authorization denied.');
            }
            $client = new KeycloakClient();
            $tokens = $client->token([
                'grant_type' => 'authorization_code', 'code' => $code,
                'redirect_uri' => $client->setting('KEYCLOAK_REDIRECT_URI'),
                'code_verifier' => $transaction['verifier'],
            ]);
            $profile = (new IdentityServiceClient())->me($tokens['access_token']);
            if ($profile['role'] !== 'admin') {
                throw new \RuntimeException('Administrator access required.');
            }
            KeycloakClient::store($tokens);
            if (!Yii::$app->user->login(new AuthenticatedUser($profile), 0)) {
                throw new \RuntimeException('Session creation failed.');
            }
            return $this->redirect(['/dashboard/index']);
        } catch (\Throwable $exception) {
            Yii::warning('OAuth login failed: ' . $exception->getMessage(), __METHOD__);
            Yii::$app->session->remove('identity_service_access_token');
            Yii::$app->session->remove('keycloak_refresh_token');
            Yii::$app->session->remove('keycloak_expires_at');
            Yii::$app->session->setFlash('error', 'Accesso non riuscito. Verifica che il tuo profilo sia abilitato e associato a un amministratore.');
            return $this->redirect(['/login/index']);
        }
    }

    public function actionLogout(): Response
    {
        $client = new KeycloakClient();
        $url = $client->endpoint('logout') . '?' . http_build_query([
            'client_id' => $client->setting('KEYCLOAK_CLIENT_ID'),
            'post_logout_redirect_uri' => $client->setting('KEYCLOAK_LOGOUT_URI'),
        ]);
        $refresh = Yii::$app->session->get('keycloak_refresh_token');
        if (is_string($refresh)) {
            try {
                $client->post('revoke', ['token' => $refresh, 'token_type_hint' => 'refresh_token']);
            } catch (\Throwable $exception) {
                Yii::warning('Keycloak revocation unavailable.', __METHOD__);
            }
        }
        Yii::$app->user->logout();
        Yii::$app->session->destroy();
        return $this->redirect($url);
    }
}
