<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\bootstrap5\NavBar;
use yii\helpers\Html;

?>
<header id="header">
    <?php NavBar::begin(
        [
            'brandImage' => '@web/images/cgp_logo.png',
            'brandImageOptions' => [
                'alt' => 'Connected Games Platform',
                'class' => 'navbar-brand-logo',
            ],
            'brandUrl' => null,
            'brandOptions' => ['class' => 'navbar-brand-logo-link'],
            'options' => ['class' => 'navbar-expand-md navbar-dark bg-dark fixed-top'],
            'innerContainerOptions' => ['class' => 'container-fluid px-0 d-flex align-items-center'],
            'collapseOptions' => false,
        ],
    ) ?>
    <div class="ms-auto d-flex align-items-center navbar-right-controls">
        <?= Html::button(
            '&#127769;',
            [
                'id' => 'theme-toggle',
                'class' => 'btn btn-link nav-link fs-5',
                'aria-label' => 'Switch to dark mode',
            ],
        ) ?>
        <?php if (!Yii::$app->user->isGuest): ?>
            <?= Html::button(
                Html::encode(Yii::$app->user->identity?->username ?? ''),
                [
                    'class' => 'btn btn-link nav-link user-menu-toggle',
                    'type' => 'button',
                    'id' => 'user-menu-toggle',
                    'aria-controls' => 'menu-right',
                    'aria-expanded' => 'false',
                ],
            ) ?>
        <?php endif; ?>
    </div>
    <?php NavBar::end() ?>
</header>
