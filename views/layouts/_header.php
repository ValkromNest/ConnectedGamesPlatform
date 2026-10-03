<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\bootstrap5\NavBar;

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
    <div class="ms-auto d-flex align-items-center">
        <?= $this->render('_menu_right') ?>
    </div>
    <?php NavBar::end() ?>
</header>
