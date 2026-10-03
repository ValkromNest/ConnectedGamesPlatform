<?php

declare(strict_types=1);

namespace app\controllers;

use yii\web\Controller;

final class AboutController extends Controller
{
    public function actionIndex(): string
    {
        return $this->render('@app/views/site/about.php');
    }
}
