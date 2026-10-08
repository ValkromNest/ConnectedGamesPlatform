<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\DartsMatch $model */

use app\enums\MatchStatus;
use app\models\DartThrow;
use app\models\MatchPlayer;
use yii\helpers\Html;
use yii\widgets\DetailView;

$status = MatchStatus::from($model->status);
$this->title = 'Dettagli partita: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Partite freccette', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
$postButton = static fn (string $label, string $route, string $class): string => Html::beginForm([$route, 'id' => $model->id], 'post', ['class' => 'd-inline'])
    . Html::submitButton($label, ['class' => 'btn ' . $class, 'data' => ['confirm' => 'Confermi questa azione?']]) . Html::endForm();
?>
<section class="darts-match-view py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h1 class="h2 mb-1"><?= Html::encode($model->name) ?></h1><p class="text-body-secondary mb-0">Dettaglio e gestione della partita.</p></div>
        <div class="d-flex flex-wrap gap-2">
            <?= Html::a('Modifica', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-secondary']) ?>
            <?php if ($status === MatchStatus::CREATED): ?><?= $postButton('Apri partita', 'open', 'btn-primary') ?><?php endif; ?>
            <?php if ($status === MatchStatus::OPEN): ?>
                <?= $postButton('Avvia partita', 'start', 'btn-success') ?>
                <?= $postButton('Termina partita', 'finish', 'btn-danger') ?>
            <?php endif; ?>
            <?php if ($status === MatchStatus::RUNNING): ?><?= $postButton('Termina partita', 'finish', 'btn-danger') ?><?php endif; ?>
        </div>
    </div>

    <?= DetailView::widget(['model' => $model, 'attributes' => [
        ['attribute' => 'status', 'label' => 'Stato', 'value' => $status->label()],
        ['attribute' => 'max_players', 'label' => 'Massimo giocatori'],
        ['attribute' => 'created_at', 'label' => 'Creata il', 'format' => ['datetime', 'php:d/m/Y H:i']],
        ['attribute' => 'started_at', 'label' => 'Avviata il', 'format' => ['datetime', 'php:d/m/Y H:i']],
        ['attribute' => 'ended_at', 'label' => 'Terminata il', 'format' => ['datetime', 'php:d/m/Y H:i']],
    ], 'options' => ['class' => 'table table-bordered detail-view bg-body mb-5']]) ?>

    <div class="card shadow-sm mb-4"><div class="card-body">
        <h2 class="h5 mb-3">Partecipanti</h2>
        <?php if ($model->matchPlayers === []): ?><p class="text-body-secondary mb-0">Nessun partecipante registrato.</p><?php else: ?>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Ordine</th><th>Giocatore</th><th>Punti rimanenti</th></tr></thead><tbody>
            <?php foreach ($model->matchPlayers as $player): /** @var MatchPlayer $player */ ?><tr><td><?= Html::encode($player->turn_order ?? '—') ?></td><td><?= Html::encode($player->user?->username ?? 'Utente non disponibile') ?></td><td><?= Html::encode($player->score) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>

    <div class="card shadow-sm"><div class="card-body">
        <h2 class="h5 mb-3">Tiri</h2>
        <?php if ($model->throws === []): ?><p class="text-body-secondary mb-0">Nessun tiro registrato.</p><?php else: ?>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Data</th><th>Giocatore</th><th>Settore</th><th>Moltiplicatore</th><th>Punti</th></tr></thead><tbody>
            <?php foreach ($model->throws as $throw): /** @var DartThrow $throw */ ?><tr><td><?= Yii::$app->formatter->asDatetime($throw->created_at) ?></td><td><?= Html::encode($throw->user?->username ?? 'Utente non disponibile') ?></td><td><?= Html::encode($throw->sector) ?></td><td><?= Html::encode($throw->multiplier) ?></td><td><?= Html::encode($throw->score) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div></div>
</section>
