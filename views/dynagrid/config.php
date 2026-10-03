<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var kartik\dynagrid\models\DynaGridConfig $model */
/** @var string $id */
/** @var bool $allowPageSetting */
/** @var array $toggleButtonGrid */
/** @var string $iconPersonalize */
/** @var string $iconSortableSeparator */

use kartik\sortable\Sortable;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Modal;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

$dynagridId = substr($model->id, 0, -9);
$visibleOptions = ArrayHelper::merge($model->widgetOptions, [
    'items' => $model->visibleColumns,
    'connected' => true,
    'options' => ['class' => 'sortable-visible'],
]);
$hiddenOptions = ArrayHelper::merge($model->widgetOptions, [
    'items' => $model->hiddenColumns,
    'connected' => true,
    'options' => ['class' => 'sortable-hidden'],
]);

Modal::begin([
    'title' => $iconPersonalize,
    'footer' => $model->footer,
    'toggleButton' => $toggleButtonGrid,
    'size' => Modal::SIZE_LARGE,
    'options' => ['id' => $id],
]);
?>
<div class="dynagrid-config-form">
    <?php $form = ActiveForm::begin(['options' => ['data-pjax' => false]]); ?>

        <?php if ($allowPageSetting): ?>
            <div class="mb-3">
                <?= $form->field($model, 'pageSize')->textInput([
                    'id' => "pageSize-{$dynagridId}",
                    'type' => 'number',
                    'min' => 0,
                ])->hint('Numero di righe da mostrare per pagina.') ?>
            </div>
        <?php endif; ?>

        <p class="fw-semibold mb-2">Colonne visualizzate</p>
        <p class="text-body-secondary small">
            Trascina le colonne tra gli elenchi per mostrarle, nasconderle o cambiarne l’ordine.
        </p>
        <div class="row g-3">
            <div class="col-md-5">
                <?= Sortable::widget($visibleOptions) ?>
            </div>
            <div class="col-md-2 d-flex justify-content-center align-items-center fs-3" aria-hidden="true">
                <?= $iconSortableSeparator ?>
            </div>
            <div class="col-md-5">
                <?= Sortable::widget($hiddenOptions) ?>
            </div>
        </div>

        <?= Html::hiddenInput('deleteFlag', 0) ?>
        <?= Html::hiddenInput($model->id, 1) ?>
        <?= Html::hiddenInput('visibleKeys') ?>
        <?= Html::activeHiddenInput($model, 'theme', ['id' => "theme-{$dynagridId}"]) ?>

    <?php ActiveForm::end(); ?>
</div>
<?php Modal::end() ?>
