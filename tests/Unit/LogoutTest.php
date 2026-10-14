<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\controllers\LoginController;
use app\models\AuthenticatedUser;
use Yii;
use yii\web\IdentityInterface;
use yii\web\Response;
use yii\web\View;

final class LogoutTest extends \Codeception\Test\Unit
{
    public function testUserMenuDisappearsAfterLogout(): void
    {
        $user = new AuthenticatedUser([
            'sub' => '8fc4f99c-d93c-4e9d-b132-7d8a71cfa225',
            'username' => 'admin',
            'role' => 'admin',
        ]);
        self::assertInstanceOf(IdentityInterface::class, $user);
        Yii::$app->user->login($user);

        $controller = new LoginController('login', Yii::$app);
        $view = new View(['context' => $controller]);
        $html = $view->render('//layouts/main.php', ['content' => 'Hello']);
        self::assertStringContainsString('id="user-menu-toggle"', $html);
        self::assertStringContainsString('data-method="post"', $html);

        $response = $controller->actionLogout();
        self::assertInstanceOf(Response::class, $response);
        self::assertTrue(Yii::$app->user->isGuest);

        $html = $view->render('//layouts/main.php', ['content' => 'Hello']);
        self::assertStringNotContainsString('id="user-menu-toggle"', $html);
    }
}
