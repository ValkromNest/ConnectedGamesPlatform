<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\Device $model */
/** @var array<int, string> $games */

$this->title = 'Aggiorna dispositivo';
$this->params['breadcrumbs'][] = ['label' => 'Elenco dispositivi', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<section class="device-update py-5">
    <h1 class="h2 mb-2">Aggiorna dispositivo</h1>
    <p class="text-body-secondary mb-4">Modifica nome, tipologia o stato del dispositivo. Il token resta invariato.</p>
    <?= $this->render('_form', ['model' => $model, 'games' => $games, 'submitLabel' => 'Salva modifiche']) ?>
</section>
