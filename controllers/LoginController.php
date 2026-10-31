<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\models\LoginForm;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

final class LoginController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'allow' => true,
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex(): Response|string
    {
        if (!Yii::$app->user->isGuest) {
            return $this->redirect(['/dashboard/index']);
        }

        $model = new LoginForm();

        if ($model->load($this->request->post()) && $model->login()) {
            return $this->redirect(['/dashboard/index']);
        }

        $model->password = '';

        return $this->render('index', ['model' => $model]);
    }

    public function actionLogout(): Response
    {
        $token = Yii::$app->session->get('identity_service_access_token');
        if (is_string($token) && $token !== '') {
            try {
                (new \app\services\IdentityServiceClient())->logout($token);
            } catch (\Throwable $exception) {
                Yii::warning('Identity service logout failed: ' . $exception->getMessage(), __METHOD__);
            }
        }
        Yii::$app->session->remove('identity_service_access_token');
        Yii::$app->user->logout();

        return $this->redirect(['/login/index']);
    }
}
