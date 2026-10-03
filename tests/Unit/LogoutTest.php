<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\controllers\SiteController;
use app\models\User;
use Yii;
use yii\base\Security;
use yii\web\IdentityInterface;
use yii\web\View;

final class LogoutTest extends \Codeception\Test\Unit
{
    public function testRenderUserMenuWhenUserIsLoggedIn(): void
    {
        $user = User::findIdentity('100');

        $controller = new SiteController(
            'site',
            Yii::$app,
            Yii::$app->mailer,
            new Security(),
        );

        $view = new View(['context' => $controller]);

        self::assertNotNull(
            $user,
            "Failed asserting that the user identity with ID '100' exists.",
        );
        self::assertInstanceOf(
            IdentityInterface::class,
            $user,
            "Failed asserting that the identity is an instance of 'Identity' class.",
        );

        Yii::$app->user->login($user);

        $html = $view->render('//layouts/main.php', ['content' => 'Hello World°']);

        self::assertStringContainsString(
            '>admin</button>',
            $html,
            'Failed asserting that the username is rendered in the user menu.',
        );
        self::assertStringContainsString(
            'id="user-menu-toggle"',
            $html,
            'Failed asserting that the user menu toggle is rendered.',
        );
        self::assertStringContainsString(
            'data-method="post"',
            $html,
            'Failed asserting that the logout action uses POST method.',
        );

        $controller->actionLogout();

        $html = $view->render('//layouts/main.php', ['content' => 'Hello World°']);

        self::assertStringNotContainsString(
            'id="user-menu-toggle"',
            $html,
            'Failed asserting that the user menu is not rendered after logout.',
        );
    }
}
