<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\Device $model */
/** @var array<int, string> $games */
/** @var string $submitLabel */

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

?>
<div class="card shadow-sm" style="max-width: 42rem;">
    <div class="card-body p-4">
        <?php $form = ActiveForm::begin(['id' => 'device-form']); ?>

            <?= $form->field($model, 'name')->textInput([
                'maxlength' => true,
                'autofocus' => true,
                'placeholder' => 'Es. TavoloFreccette1',
            ]) ?>

            <?= $form->field($model, 'game_id')->dropDownList($games, [
                'prompt' => 'Seleziona un gioco',
            ])->label('Tipologia') ?>

            <?php if (!$model->isNewRecord): ?>
                <?= $form->field($model, 'is_enabled')->dropDownList([
                    1 => 'Abilitato',
                    0 => 'Disabilitato',
                ])->label('Stato') ?>
                <div class="mt-3">
                    <?= Html::button('Mostra token', [
                        'type' => 'button',
                        'class' => 'btn btn-outline-secondary',
                        'id' => 'show-device-token',
                        'data-url' => \yii\helpers\Url::to(['device/show-token', 'id' => $model->id]),
                        'data-csrf-token' => Yii::$app->request->csrfToken,
                    ]) ?>
                    <div id="device-token-result" class="mt-3" hidden aria-live="polite">
                        <div id="device-token-error" class="alert alert-danger mb-0" hidden></div>
                        <div id="device-token-value" class="alert alert-warning mb-0" hidden>
                            <strong>Token dispositivo</strong>
                            <div class="mt-2"><code id="device-token-text" class="user-select-all"></code></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="d-flex gap-2 mt-4">
                <?= Html::submitButton($submitLabel, ['class' => 'btn btn-primary']) ?>
                <?= Html::a('Annulla', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
            </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>

<?php if (!$model->isNewRecord): ?>
    <?php
    $this->registerJs(<<<'JS'
    (() => {
        const button = document.getElementById('show-device-token');
        const result = document.getElementById('device-token-result');
        const valueBox = document.getElementById('device-token-value');
        const errorBox = document.getElementById('device-token-error');
        const tokenText = document.getElementById('device-token-text');

        button.addEventListener('click', async () => {
            button.disabled = true;
            result.hidden = true;
            valueBox.hidden = true;
            errorBox.hidden = true;

            try {
                const response = await fetch(button.dataset.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-Token': button.dataset.csrfToken,
                    },
                });
                const data = await response.json();
                result.hidden = false;

                if (!response.ok) {
                    errorBox.textContent = data.error || 'Non è stato possibile recuperare il token.';
                    errorBox.hidden = false;
                    return;
                }

                tokenText.textContent = data.token;
                valueBox.hidden = false;
            } catch (error) {
                result.hidden = false;
                errorBox.textContent = 'Errore durante il recupero del token.';
                errorBox.hidden = false;
            } finally {
                button.disabled = false;
            }
        });
    })();
    JS);
    ?>
<?php endif; ?>
