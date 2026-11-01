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
                <div class="d-flex flex-column justify-content-between p-4 p-lg-5 w-100">
                    <div>
                        <?= Html::img(
                            Yii::getAlias('@web/images/yii3_full_white_for_dark.svg'),
                            [
                                'alt' => 'Connected Games Platform',
                                'class' => 'mb-4',
                                'height' => 40,
                            ],
                        ) ?>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-3 login-brand-title">
                            Connected<br>Games
                        </h2>
                        <p class="opacity-75 mb-0 login-brand-text">
                            Area riservata per la gestione delle partite di Freccette.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form panel -->
            <div class="col-md-7">
                <div class="p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <!-- Mobile-only logo -->
                        <div class="d-md-none mb-3">
                            <?= Html::img(
                                Yii::getAlias('@web/images/yii3_full_black_for_light.svg'),
                                [
                                'alt' => 'Connected Games Platform',
                                    'class' => 'login-mobile-logo',
                                    'height' => 36,
                                ],
                            ) ?>
                        </div>
                        <h1 class="h3 fw-bold mb-1"><?= Html::encode($this->title) ?></h1>
                        <p class="text-body-secondary small">Accedi con le credenziali di amministratore</p>
                    </div>

                    <div class="d-grid">
                        <?= Html::a('Accedi', ['/login/authorize'], ['class' => 'btn login-btn btn-lg rounded-3 text-white']) ?>
                    </div>

                    <p class="text-body-secondary text-center mt-3 mb-0 small">
                        Accesso riservato agli amministratori della piattaforma.
                    </p>

                </div>
            </div>

        </div>
    </div>
</div>
