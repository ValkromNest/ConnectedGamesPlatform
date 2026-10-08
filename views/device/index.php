<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\GridView;
use yii\helpers\Html;

$this->title = 'Elenco dispositivi';
$this->params['breadcrumbs'][] = $this->title;
?>
<section class="device-index py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1">Gestore dispositivi</h1>
            <p class="text-body-secondary mb-0">Dispositivi registrati e tipologia di gioco associata.</p>
        </div>
        <?= Html::a('Crea nuovo', ['create'], ['class' => 'btn btn-primary']) ?>
    </div>

    <?php if (Yii::$app->session->hasFlash('success')): ?>
        <div class="alert alert-success" role="alert"><?= Html::encode(Yii::$app->session->getFlash('success')) ?></div>
    <?php endif; ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            [
                'label' => 'Nome',
                'format' => 'raw',
                'value' => static fn (\app\models\Device $model): string => Html::a(
                    Html::encode($model->name),
                    ['update', 'id' => $model->id],
                    ['class' => 'btn btn-outline-primary w-100 text-start'],
                ),
            ],
            [
                'label' => 'Tipologia',
                'value' => static fn (\app\models\Device $model): string => $model->game?->name ?? 'Gioco non disponibile',
            ],
            [
                'attribute' => 'is_enabled',
                'label' => 'Stato',
                'value' => static fn (\app\models\Device $model): string => $model->is_enabled ? 'Abilitato' : 'Disabilitato',
            ],
            [
                'label' => 'Token',
                'value' => static fn (\app\models\Device $model): string => $model->token_hash !== '' ? 'Configurato' : 'Assente',
            ],
        ],
        'layout' => "{items}\n{pager}",
        'tableOptions' => ['class' => 'table table-hover align-middle mb-0'],
        'emptyText' => 'Non sono ancora stati registrati dispositivi.',
    ]) ?>
</section>
