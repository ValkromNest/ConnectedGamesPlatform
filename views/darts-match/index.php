<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use app\enums\MatchStatus;
use app\models\GameMatch;
use kartik\dynagrid\DynaGrid;
use yii\helpers\Html;

$this->title = 'Partite freccette';
$this->params['breadcrumbs'][] = $this->title;
?>
<section class="darts-match-index py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1">Partite freccette</h1>
            <p class="text-body-secondary mb-0">Elenco delle partite create per il gioco Freccette.</p>
        </div>
        <?= Html::a('Nuova partita', ['create'], ['class' => 'btn btn-primary']) ?>
    </div>

    <?= DynaGrid::widget([
        'options' => ['id' => 'darts-matches-dynagrid'],
        'storage' => DynaGrid::TYPE_SESSION,
        'theme' => 'simple-default',
        'allowThemeSetting' => false,
        'showFilter' => false,
        'showSort' => false,
        'iconPersonalize' => 'Personalizza tabella',
        'iconSortableSeparator' => '&harr;',
        'toggleButtonGrid' => ['class' => 'btn btn-outline-secondary btn-sm'],
        'columns' => [
            'name:text:Nome',
            [
                'attribute' => 'status',
                'label' => 'Stato',
                'value' => static fn (GameMatch $model): string => MatchStatus::from($model->status)->label(),
            ],
            [
                'attribute' => 'max_players',
                'label' => 'Max giocatori',
            ],
            [
                'attribute' => 'created_at',
                'label' => 'Creata il',
                'format' => ['datetime', 'php:d/m/Y H:i'],
            ],
        ],
        'gridOptions' => [
            'dataProvider' => $dataProvider,
            'layout' => "{dynagrid}\n{items}\n{pager}",
            'tableOptions' => ['class' => 'table table-hover align-middle mb-0'],
            'emptyText' => 'Non sono ancora state create partite di freccette.',
            'export' => false,
            'pjax' => false,
        ],
    ]) ?>
</section>
