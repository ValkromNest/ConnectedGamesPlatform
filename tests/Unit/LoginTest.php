<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\controllers\LoginController;
use app\models\AuthenticatedUser;
use Yii;
use yii\web\Response;

final class LoginTest extends \Codeception\Test\Unit
{
    public function testAuthenticatedUserIsRedirectedFromLoginPage(): void
    {
        Yii::$app->user->login(new AuthenticatedUser([
            'sub' => '8fc4f99c-d93c-4e9d-b132-7d8a71cfa225',
            'username' => 'admin',
            'role' => 'admin',
        ]));

        $response = (new LoginController('login', Yii::$app))->actionIndex();

        self::assertInstanceOf(Response::class, $response);
        self::assertFalse(Yii::$app->user->isGuest);
    }
}
