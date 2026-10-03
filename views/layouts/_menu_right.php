<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

?>
<?= Html::button(
    '&#127769;',
    [
        'id' => 'theme-toggle',
        'class' => 'btn btn-link nav-link fs-5',
        'aria-label' => 'Switch to dark mode',
    ],
) ?>
<?php if (!Yii::$app->user->isGuest): ?>
    <div class="dropdown ms-2">
        <?= Html::button(
            Html::encode(Yii::$app->user->identity?->username ?? ''),
            [
                'class' => 'btn btn-link nav-link dropdown-toggle user-menu-toggle',
                'type' => 'button',
                'id' => 'user-menu-toggle',
                'data-bs-toggle' => 'dropdown',
                'aria-expanded' => 'false',
            ],
        ) ?>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="user-menu-toggle">
            <li>
                <?= Html::beginForm(['/site/logout'], 'post') ?>
                <?= Html::submitButton('Logout', ['class' => 'dropdown-item']) ?>
                <?= Html::endForm() ?>
            </li>
        </ul>
    </div>
<?php endif; ?>
