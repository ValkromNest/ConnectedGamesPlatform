<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

$this->registerJsFile(Url::to('@web/js/user-menu.js'), ['position' => View::POS_END]);
$isThemeManager = Yii::$app->controller?->route === 'gestore-temi/index';

?>
<aside
    id="menu-right"
    class="app-sidebar app-sidebar-right"
    aria-labelledby="user-menu-toggle"
    aria-hidden="true"
>
    <div class="app-sidebar-body">
        <div class="app-sidebar-brand-divider" aria-hidden="true"></div>
        <nav class="app-sidebar-navigation" aria-label="Menu utente">
            <ul class="nav flex-column gap-1">
                <li class="nav-item">
                    <?= Html::a(
                        'Gestore temi',
                        ['/gestore-temi/index'],
                        ['class' => 'nav-link app-sidebar-link' . ($isThemeManager ? ' active' : '')],
                    ) ?>
                </li>
                <li class="nav-item">
                    <?= Html::a(
                        'Logout',
                        ['/login/logout'],
                        [
                            'class' => 'nav-link app-sidebar-link',
                            'data-method' => 'post',
                        ],
                    ) ?>
                </li>
            </ul>
        </nav>
    </div>
</aside>
