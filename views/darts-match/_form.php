<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\DartsMatch $model */
/** @var string $submitLabel */
/** @var array<int, string> $devices */

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

?>
<div class="card shadow-sm" style="max-width: 42rem;">
    <div class="card-body p-4">
        <?php $form = ActiveForm::begin(['id' => 'darts-match-form']); ?>

            <?= $form->field($model, 'name')->textInput([
                'maxlength' => true,
                'autofocus' => true,
                'placeholder' => 'Es. Sfida del venerdì',
            ]) ?>

            <div class="mb-3">
                <?= Html::label('Gioco', 'darts-game', ['class' => 'form-label']) ?>
                <?= Html::textInput('darts-game', 'Freccette', [
                    'id' => 'darts-game',
                    'class' => 'form-control',
                    'disabled' => true,
                ]) ?>
            </div>

            <?= $form->field($model, 'max_players')->dropDownList([
                1 => '1 giocatore',
                2 => '2 giocatori',
            ]) ?>

            <?= $form->field($model, 'device_id')->dropDownList($devices, [
                'prompt' => 'Seleziona un dispositivo',
            ])->label('Dispositivo') ?>

            <div class="d-flex gap-2 mt-4">
                <?= Html::submitButton($submitLabel, ['class' => 'btn btn-primary']) ?>
                <?= Html::a('Annulla', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
            </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>
