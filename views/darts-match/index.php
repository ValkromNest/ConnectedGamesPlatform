<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var app\models\DartsMatch $searchModel */

use app\enums\MatchStatus;
use app\models\DartsMatch;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\Pjax;

$this->title = 'Partite freccette';
$this->params['breadcrumbs'][] = $this->title;
$this->registerJs(<<<'JS'
let dartsMatchFilterTimer;

function applyDartsMatchFilters() {
    const $grid = $('#darts-matches-grid');

    if ($grid.length > 0 && typeof $grid.yiiGridView === 'function') {
        $grid.yiiGridView('applyFilter');
    }
}

$(document).on('input', '#darts-matches-pjax .filters input', function () {
    clearTimeout(dartsMatchFilterTimer);
    dartsMatchFilterTimer = setTimeout(function () {
        applyDartsMatchFilters();
    }, 350);
});

$(document).on('change', '#darts-matches-pjax .filters select', function () {
    applyDartsMatchFilters();
});
JS, View::POS_READY);
?>
<section class="darts-match-index py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1">Partite freccette</h1>
            <p class="text-body-secondary mb-0">Elenco delle partite create per il gioco Freccette.</p>
        </div>
        <?= Html::a('Nuova partita', ['create'], ['class' => 'btn btn-primary']) ?>
    </div>

    <?php Pjax::begin([
        'id' => 'darts-matches-pjax',
        'timeout' => false,
        'enablePushState' => false,
    ]) ?>
    <?= GridView::widget([
        'id' => 'darts-matches-grid',
        'columns' => [
            [
                'attribute' => 'name',
                'label' => 'Nome',
                'format' => 'raw',
                'value' => static fn (DartsMatch $model): string => Html::a(
                    Html::encode($model->name),
                    ['view', 'id' => $model->id],
                    ['class' => 'btn btn-outline-primary btn-sm w-100 text-center'],
                ),
            ],
            [
                'attribute' => 'status',
                'label' => 'Stato',
                'filter' => [
                    MatchStatus::CREATED->value => MatchStatus::CREATED->label(),
                    MatchStatus::OPEN->value => MatchStatus::OPEN->label(),
                    MatchStatus::RUNNING->value => MatchStatus::RUNNING->label(),
                    MatchStatus::FINISHED->value => MatchStatus::FINISHED->label(),
                ],
                'value' => static fn (DartsMatch $model): string => MatchStatus::from($model->status)->label(),
            ],
            [
                'label' => 'Dettagli',
                'format' => 'raw',
                'value' => static fn (DartsMatch $model): string => Html::a('Dettagli', ['view', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm']),
            ],
            [
                'attribute' => 'max_players',
                'label' => 'Max giocatori',
                'filter' => [
                    1 => '1 giocatore',
                    2 => '2 giocatori',
                ],
            ],
            [
                'attribute' => 'created_at',
                'label' => 'Creata il',
                'format' => ['datetime', 'php:d/m/Y H:i'],
                'filterInputOptions' => [
                    'class' => 'form-control',
                    'type' => 'date',
                ],
            ],
        ],
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'layout' => "{items}\n{pager}",
        'tableOptions' => ['class' => 'table table-hover align-middle mb-0'],
        'emptyText' => 'Non sono ancora state create partite di freccette.',
        'filterOnFocusOut' => false,
    ]) ?>
    <?php Pjax::end() ?>
</section>
