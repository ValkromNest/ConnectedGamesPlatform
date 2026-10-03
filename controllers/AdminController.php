<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;

abstract class AdminController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => static fn (): bool => Yii::$app->user->identity?->isAdministrator() ?? false,
                    ],
                ],
            ],
        ];
    }
}
