<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

$this->title = 'Dashboard';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="site-dashboard py-5">
    <h1 class="h2 mb-3">Dashboard</h1>
    <p class="text-body-secondary mb-0">
        Benvenuto, <?= Html::encode(Yii::$app->user->identity->username) ?>. La gestione delle partite verrà aggiunta nella Fase 4.
    </p>
</div>
