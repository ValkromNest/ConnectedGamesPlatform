<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\DartsMatch $model */

$this->title = 'Modifica partita freccette';
$this->params['breadcrumbs'] = [
    ['label' => 'Partite freccette', 'url' => ['index']],
    $this->title,
];
?>
<section class="darts-match-update py-5">
    <div class="mb-4">
        <h1 class="h2 mb-1">Modifica partita freccette</h1>
        <p class="text-body-secondary mb-0">
            Puoi aggiornare il nome e il numero massimo di giocatori. Gioco e stato restano invariati.
        </p>
    </div>

    <?= $this->render('_form', ['model' => $model, 'submitLabel' => 'Salva modifiche']) ?>
</section>
