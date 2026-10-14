<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\LoginForm;
use Yii;

final class LoginFormTest extends \Codeception\Test\Unit
{
    public function testLoginRequiresUsernameAndPassword(): void
    {
        $model = new LoginForm();

        verify($model->login())->false();
        verify(Yii::$app->user->isGuest)->true();
        verify($model->errors)->arrayHasKey('username');
        verify($model->errors)->arrayHasKey('password');
    }
}
