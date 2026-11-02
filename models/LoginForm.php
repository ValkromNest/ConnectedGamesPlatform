<?php

declare(strict_types=1);

namespace app\models;

use app\services\IdentityServiceClient;
use app\services\IdentityServiceException;
use Yii;
use yii\base\Model;

final class LoginForm extends Model
{
    public string $username = '';
    public string $password = '';
    private ?array $authentication = null;

    public function rules(): array
    {
        return [
            [['username', 'password'], 'required'],
            [['username'], 'string', 'max' => 100],
            ['password', 'authenticate'],
        ];
    }

    public function authenticate(string $attribute): void
    {
        if ($this->hasErrors()) {
            return;
        }
        try {
            $this->authentication = (new IdentityServiceClient())->login($this->username, $this->password);
        } catch (IdentityServiceException $exception) {
            $message = match ($exception->statusCode) {
                401 => 'Username o password non corretti.',
                429 => 'Troppi tentativi di accesso. Attendi qualche minuto e riprova.',
                default => 'Il servizio di login non è disponibile. Riprova tra poco.',
            };
            $this->addError($attribute, $message);
            return;
        }

        $user = $this->authentication['user'];
        $token = $this->authentication['accessToken'];
        if ($user['role'] !== 'admin' || $token === '') {
            if ($token !== '') {
                try {
                    (new IdentityServiceClient())->logout($token);
                } catch (IdentityServiceException) {
                    // The token expires automatically if it cannot be revoked here.
                }
            }
            $this->authentication = null;
            $this->addError($attribute, 'Accesso riservato agli amministratori.');
        }
    }

    public function login(): bool
    {
        if (!$this->validate() || $this->authentication === null) {
            return false;
        }
        Yii::$app->session->set('identity_service_access_token', $this->authentication['accessToken']);
        $identity = new AuthenticatedUser($this->authentication['user']);
        if (!Yii::$app->user->login($identity, 0)) {
            Yii::$app->session->remove('identity_service_access_token');
            return false;
        }
        return true;
    }
}
