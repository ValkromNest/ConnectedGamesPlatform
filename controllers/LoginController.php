<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\models\LoginForm;
use yii\base\Security;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

final class LoginController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly Security $security,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
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

        $model = new LoginForm($this->security);

        if ($model->load($this->request->post()) && $model->login()) {
            return $this->redirect(['/dashboard/index']);
        }

        $model->password = '';

        return $this->render('index', ['model' => $model]);
    }

    public function actionLogout(): Response
    {
        Yii::$app->user->logout();

        return $this->redirect(['/login/index']);
    }
}
