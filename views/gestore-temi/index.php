<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $coloreDiurno */
/** @var string $coloreNotturno */
/** @var string|null $error */

use yii\helpers\Html;

$this->title = 'Gestore temi';

?>
<section class="site-theme-manager py-5">
    <h1 class="h2 mb-2">Gestore temi</h1>
    <p class="text-body-secondary mb-4">
        Scegli il colore principale da usare per il tema diurno e per quello notturno.
    </p>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= Html::encode($error) ?></div>
    <?php endif; ?>

    <?= Html::beginForm(['/gestore-temi/index'], 'post', ['id' => 'theme-settings-form']) ?>
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
        <div class="row g-4">
            <div class="col-md-6">
                <?= Html::label('Colore tema diurno', 'colore_diurno', ['class' => 'form-label']) ?>
                <?= Html::input('color', 'colore_diurno', $coloreDiurno, [
                    'class' => 'form-control form-control-color',
                    'id' => 'colore_diurno',
                ]) ?>
            </div>
            <div class="col-md-6">
                <?= Html::label('Colore tema notturno', 'colore_notturno', ['class' => 'form-label']) ?>
                <?= Html::input('color', 'colore_notturno', $coloreNotturno, [
                    'class' => 'form-control form-control-color',
                    'id' => 'colore_notturno',
                ]) ?>
            </div>
        </div>

        <div class="mt-4">
            <?= Html::submitButton('Salva colori', ['class' => 'btn btn-primary']) ?>
        </div>
    <?= Html::endForm() ?>
</section>
