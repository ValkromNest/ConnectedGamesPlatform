<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;

$isDashboard = Yii::$app->controller?->route === 'dashboard/index';
$isDartsMatchSection = in_array(Yii::$app->controller?->id, ['darts-match'], true);
$isDartsMatchIndex = Yii::$app->controller?->route === 'darts-match/index';
$isDartsMatchCreate = Yii::$app->controller?->route === 'darts-match/create';
$isDeviceSection = Yii::$app->controller?->id === 'device';
$isDeviceIndex = Yii::$app->controller?->route === 'device/index';
$isDeviceCreate = Yii::$app->controller?->route === 'device/create';

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
            <li class="nav-item app-sidebar-submenu-item">
                <details class="app-sidebar-submenu"<?= $isDartsMatchSection ? ' open' : '' ?>>
                    <summary class="nav-link app-sidebar-link<?= $isDartsMatchSection ? ' active' : '' ?>">
                        Partite freccette
                    </summary>
                    <ul class="nav flex-column app-sidebar-submenu-links">
                        <li class="nav-item">
                            <?= Html::a(
                                'Nuova partita',
                                ['/darts-match/create'],
                                ['class' => 'nav-link app-sidebar-link' . ($isDartsMatchCreate ? ' active' : '')],
                            ) ?>
                        </li>
                        <li class="nav-item">
                            <?= Html::a(
                                'Elenco partite',
                                ['/darts-match/index'],
                                ['class' => 'nav-link app-sidebar-link' . ($isDartsMatchIndex ? ' active' : '')],
                            ) ?>
                        </li>
                    </ul>
                </details>
            </li>
            <li class="nav-item app-sidebar-submenu-item">
                <details class="app-sidebar-submenu"<?= $isDeviceSection ? ' open' : '' ?>>
                    <summary class="nav-link app-sidebar-link<?= $isDeviceSection ? ' active' : '' ?>">
                        Gestore Dispositivi
                    </summary>
                    <ul class="nav flex-column app-sidebar-submenu-links">
                        <li class="nav-item">
                            <?= Html::a(
                                'Crea nuovo',
                                ['/device/create'],
                                ['class' => 'nav-link app-sidebar-link' . ($isDeviceCreate ? ' active' : '')],
                            ) ?>
                        </li>
                        <li class="nav-item">
                            <?= Html::a(
                                'Elenco dispositivi',
                                ['/device/index'],
                                ['class' => 'nav-link app-sidebar-link' . ($isDeviceIndex ? ' active' : '')],
                            ) ?>
                        </li>
                    </ul>
                </details>
            </li>
        </ul>
    </nav>
</aside>
