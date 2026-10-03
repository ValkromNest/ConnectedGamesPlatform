<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

$isDashboard = Yii::$app->controller?->route === 'dashboard/index';

?>
<aside id="menu-left" class="app-sidebar" aria-label="Navigazione principale">
    <div class="app-sidebar-brand-divider" aria-hidden="true"></div>
    <nav class="app-sidebar-navigation">
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <?= Html::a(
                    'Dashboard',
                    ['/dashboard/index'],
                    ['class' => 'nav-link app-sidebar-link' . ($isDashboard ? ' active' : '')],
                ) ?>
            </li>
        </ul>
    </nav>
</aside>
