<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\Device $model */
/** @var array<int, string> $games */

use yii\helpers\Html;

$this->title = 'Crea nuovo dispositivo';
$this->params['breadcrumbs'][] = ['label' => 'Elenco dispositivi', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<section class="device-create py-5">
    <h1 class="h2 mb-2">Crea nuovo dispositivo</h1>
    <p class="text-body-secondary mb-4">Il token verrà generato automaticamente e potrà essere mostrato dalla pagina di modifica.</p>
    <?= $this->render('_form', ['model' => $model, 'games' => $games, 'submitLabel' => 'Crea dispositivo']) ?>
</section>
