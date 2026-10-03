<?php

declare(strict_types=1);

namespace app\controllers;

final class DashboardController extends AdminController
{
    public function actionIndex(): string
    {
        return $this->render('index');
    }
}
