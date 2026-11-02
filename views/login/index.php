<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */

/** @var app\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Connected Games Platform';
$this->params['breadcrumbs'][] = $this->title;
$this->params['meta_description'] = 'Accesso amministrativo alla Connected Games Platform.';
$this->params['meta_keywords'] = 'connected games platform, accesso amministratore';
$htmlIcon = <<<HTML
{label}<div class="input-group"><span class="input-group-text" aria-hidden="true">%s</span>{input}</div>{error}{hint}
HTML;
$labelOptions = ['class' => 'form-label fw-semibold small'];
?>
<div class="site-login d-flex align-items-center justify-content-center py-5">
    <div class="card border-0 overflow-hidden login-split-card">
        <div class="row g-0">

            <!-- Brand panel -->
            <div class="col-md-5 d-none d-md-flex login-brand-panel text-white">
                <div class="d-flex flex-column justify-content-center text-start p-4 p-lg-5 w-100">
                    <h2 class="fw-bold mb-0 login-brand-title">
                        Connected<br>Games<br>Platform
                    </h2>
                </div>
            </div>

            <!-- Form panel -->
            <div class="col-md-7 login-form-panel d-flex flex-column justify-content-center">
                <div class="p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <!-- Mobile-only logo -->
                        <div class="d-md-none mb-3">
                            <?= Html::img(
                                Yii::getAlias('@web/images/cgp_logo.png'),
                                [
                                'alt' => 'Connected Games Platform',
                                    'class' => 'login-mobile-logo',
                                    'height' => 36,
                                ],
                            ) ?>
                        </div>
                        <h1 class="h3 fw-bold mb-1">Accesso Amministrativo</h1>
                        <p class="text-body-secondary small">Accedi con le credenziali di amministratore</p>
                    </div>

                    <div class="d-grid">
                        <?= Html::a('Accedi', ['/login/authorize'], ['class' => 'btn login-btn btn-lg rounded-3']) ?>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
