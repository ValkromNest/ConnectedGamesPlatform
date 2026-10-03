<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\GameMatch $model */

$this->title = 'Nuova partita freccette';
$this->params['breadcrumbs'] = [
    ['label' => 'Partite freccette', 'url' => ['index']],
    $this->title,
];
?>
<section class="darts-match-create py-5">
    <div class="mb-4">
        <h1 class="h2 mb-1">Nuova partita freccette</h1>
        <p class="text-body-secondary mb-0">
            La partita verrà creata nello stato <strong>Creata</strong>; potrà essere aperta e avviata nelle prossime fasi.
        </p>
    </div>

    <?= $this->render('_form', ['model' => $model, 'submitLabel' => 'Crea partita']) ?>
</section>
