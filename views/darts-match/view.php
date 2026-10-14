<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\DartsMatch $model */
/** @var array<int, string> $availablePlayers */
/** @var array<string, mixed>|null $gameState */

use app\enums\MatchStatus;
use app\models\MatchPlayer;
use yii\helpers\Html;
use yii\widgets\DetailView;

$status = MatchStatus::from($model->status);
$players = $gameState['players'] ?? (in_array((int) $model->status, [MatchStatus::RUNNING->value, MatchStatus::FINISHED->value], true)
    ? []
    : array_map(static fn (MatchPlayer $player): array => [
    'playerId' => (string) $player->user_id,
    'displayName' => (string) ($player->user?->username ?? 'Utente non disponibile'),
    'remainingScore' => (int) $player->score,
    'turnOrder' => (int) $player->turn_order,
], $model->matchPlayers));
$turn = $gameState['turn'] ?? null;
$winnerId = $gameState['winnerPlayerId'] ?? null;
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
            <?php if (in_array((int) $model->status, [MatchStatus::CREATED->value, MatchStatus::OPEN->value], true)): ?><?= Html::a('Modifica', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-secondary']) ?><?php endif; ?>
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
        ['label' => 'Dispositivo', 'value' => $model->device?->name ?? '—'],
        ['attribute' => 'created_at', 'label' => 'Creata il', 'format' => ['datetime', 'php:d/m/Y H:i']],
        ['attribute' => 'started_at', 'label' => 'Avviata il', 'format' => ['datetime', 'php:d/m/Y H:i']],
        ['attribute' => 'ended_at', 'label' => 'Terminata il', 'format' => ['datetime', 'php:d/m/Y H:i']],
    ], 'options' => ['class' => 'table table-bordered detail-view bg-body mb-5']]) ?>

    <div class="card shadow-sm mb-4"><div class="card-body">
        <h2 class="h5 mb-3">Partecipanti</h2>
        <?php if ($players === []): ?><p class="text-body-secondary mb-0"><?= in_array((int) $model->status, [MatchStatus::RUNNING->value, MatchStatus::FINISHED->value], true) ? 'Stato del gioco non disponibile dal servizio.' : 'Nessun partecipante registrato.' ?></p><?php else: ?>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Ordine</th><th>Giocatore</th><th>Punti rimanenti</th><?php if ($status === MatchStatus::OPEN): ?><th></th><?php endif; ?></tr></thead><tbody>
            <?php foreach ($players as $index => $player): ?><tr><td><?= Html::encode($player['turnOrder'] ?? $index + 1) ?></td><td><?= Html::encode($player['displayName'] ?? '') ?><?= (string) ($player['playerId'] ?? '') === (string) $winnerId ? ' (vincitore)' : '' ?></td><td><?= Html::encode($player['remainingScore'] ?? 501) ?></td><?php if ($status === MatchStatus::OPEN): ?><td class="text-end"><?= Html::beginForm(['remove-player', 'id' => $model->id, 'userId' => $player['playerId']], 'post', ['class' => 'd-inline']) ?><?= Html::submitButton('Rimuovi', ['class' => 'btn btn-outline-danger btn-sm', 'data' => ['confirm' => 'Rimuovere questo player dalla partita?']]) ?><?= Html::endForm() ?></td><?php endif; ?></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <?php if ($status === MatchStatus::RUNNING && is_array($turn) && isset($turn['playerId'])): ?>
            <p class="mt-3 mb-0"><strong>Turno:</strong> <?= Html::encode($turn['playerId']) ?> · freccette rimanenti: <?= Html::encode($turn['dartsRemaining'] ?? '—') ?></p>
        <?php endif; ?>
        <?php if ($status === MatchStatus::OPEN && count($model->matchPlayers) < (int) $model->max_players && $availablePlayers !== []): ?>
            <button type="button" class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#add-player-modal">Aggiungi player</button>
            <div class="modal fade" id="add-player-modal" tabindex="-1" aria-labelledby="add-player-modal-label" aria-hidden="true">
                <div class="modal-dialog"><div class="modal-content">
                    <?= Html::beginForm(['add-player', 'id' => $model->id], 'post') ?>
                    <div class="modal-header"><h2 class="modal-title fs-5" id="add-player-modal-label">Aggiungi player</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button></div>
                    <div class="modal-body">
                        <?= Html::label('Player', 'add-player-user-id', ['class' => 'form-label']) ?>
                        <?= Html::dropDownList('user_id', null, $availablePlayers, ['id' => 'add-player-user-id', 'class' => 'form-select', 'prompt' => 'Seleziona un player', 'required' => true]) ?>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button><?= Html::submitButton('Aggiungi', ['class' => 'btn btn-primary']) ?></div>
                    <?= Html::endForm() ?>
                </div></div>
            </div>
        <?php endif; ?>
    </div></div>

</section>
