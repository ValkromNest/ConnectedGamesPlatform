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
    'playerId' => (string) $player->identity_subject_id,
    'displayName' => (string) $player->display_name,
    'remainingScore' => (int) $player->score,
    'turnOrder' => (int) $player->turn_order,
], $model->matchPlayers));
$turn = $gameState['turn'] ?? null;
$winnerId = $gameState['winnerPlayerId'] ?? null;
$throws = $gameState['throws'] ?? [];
$playerNames = [];
foreach ($players as $player) {
    $playerNames[(string) ($player['playerId'] ?? '')] = (string) ($player['displayName'] ?? '');
}
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

    <div id="match-participants" class="card shadow-sm mb-4"><div class="card-body">
        <h2 class="h5 mb-3">Partecipanti</h2>
        <?php if ($players === []): ?><p class="text-body-secondary mb-0"><?= in_array((int) $model->status, [MatchStatus::RUNNING->value, MatchStatus::FINISHED->value], true) ? 'Stato del gioco non disponibile dal servizio.' : 'Nessun partecipante registrato.' ?></p><?php else: ?>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Ordine</th><th>Giocatore</th><th>Punti rimanenti</th><?php if ($status === MatchStatus::OPEN): ?><th></th><?php endif; ?></tr></thead><tbody>
            <?php foreach ($players as $index => $player): ?><tr><td><?= Html::encode($player['turnOrder'] ?? $index + 1) ?></td><td><?= Html::encode($player['displayName'] ?? '') ?><?= (string) ($player['playerId'] ?? '') === (string) $winnerId ? ' (vincitore)' : '' ?></td><td><?= Html::encode($player['remainingScore'] ?? 501) ?></td><?php if ($status === MatchStatus::OPEN): ?><td class="text-end"><?= Html::beginForm(['remove-player', 'id' => $model->id, 'userId' => $player['playerId']], 'post', ['class' => 'd-inline']) ?><?= Html::submitButton('Rimuovi', ['class' => 'btn btn-outline-danger btn-sm', 'data' => ['confirm' => 'Rimuovere questo player dalla partita?']]) ?><?= Html::endForm() ?></td><?php endif; ?></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <?php if ($status === MatchStatus::RUNNING && is_array($turn) && isset($turn['playerId'])): ?>
            <p class="mt-3 mb-0"><strong>Turno:</strong> <?= Html::encode($playerNames[(string) $turn['playerId']] ?? 'Giocatore sconosciuto') ?> · freccette rimanenti: <?= Html::encode($turn['dartsRemaining'] ?? '—') ?></p>
        <?php endif; ?>
        <?php if ($status === MatchStatus::OPEN && count($model->matchPlayers) < (int) $model->max_players && $availablePlayers !== []): ?>
            <button type="button" class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#add-player-modal">Aggiungi player</button>
            <div class="modal fade" id="add-player-modal" tabindex="-1" aria-labelledby="add-player-modal-label" aria-hidden="true">
                <div class="modal-dialog"><div class="modal-content">
                    <?= Html::beginForm(['add-player', 'id' => $model->id], 'post') ?>
                    <div class="modal-header"><h2 class="modal-title fs-5" id="add-player-modal-label">Aggiungi player</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button></div>
                    <div class="modal-body">
                        <?= Html::label('Player', 'add-player-subject-id', ['class' => 'form-label']) ?>
                        <?= Html::dropDownList('player_subject_id', null, $availablePlayers, ['id' => 'add-player-subject-id', 'class' => 'form-select', 'prompt' => 'Seleziona un player', 'required' => true]) ?>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button><?= Html::submitButton('Aggiungi', ['class' => 'btn btn-primary']) ?></div>
                    <?= Html::endForm() ?>
                </div></div>
            </div>
        <?php endif; ?>
    </div></div>

    <div id="match-throws">
    <?php if (in_array((int) $model->status, [MatchStatus::RUNNING->value, MatchStatus::FINISHED->value], true)): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                    <h2 class="h5 mb-0">Tiri effettuati</h2>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="match-throws-toggle" aria-expanded="false" aria-controls="match-throws-table">mostra tutti</button>
                </div>
                <?php if ($throws === []): ?>
                    <p class="text-body-secondary mb-0">Non sono ancora stati registrati tiri.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="match-throws-table" class="table table-hover align-middle mb-0">
                            <thead><tr><th>#</th><th>Giocatore</th><th>Settore</th><th>Moltiplicatore</th><th>Punti</th><th>Esito</th><th>Registrato il</th></tr></thead>
                            <tbody>
                            <?php $visibleThrowCount = 0; foreach (array_reverse($throws, true) as $index => $throw): ?>
                                <?php
                                $sector = (int) ($throw['sector'] ?? 0);
                                $multiplier = (int) ($throw['multiplier'] ?? 1);
                                $resultLabels = ['scored' => 'Valido', 'missed' => 'Mancato', 'bust' => 'Bust', 'checkout' => 'Vittoria'];
                                $result = (string) ($throw['result'] ?? '');
                                ?>
                                <tr data-match-throw<?= $visibleThrowCount++ >= 6 ? ' hidden' : '' ?>>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= Html::encode($playerNames[(string) ($throw['playerId'] ?? '')] ?? ('Player ' . ($throw['playerId'] ?? ''))) ?></td>
                                    <td><?= $sector === 0 ? 'Mancato' : ($sector === 25 ? 'Bull' : $sector) ?></td>
                                    <td><?= $sector === 0 ? '—' : ($multiplier === 1 ? 'Singolo' : ($multiplier === 2 ? 'Doppio' : 'Triplo')) ?></td>
                                    <td><?= Html::encode($throw['score'] ?? 0) ?></td>
                                    <td><?= Html::encode($resultLabels[$result] ?? $result) ?></td>
                                    <td><?= !empty($throw['createdAt']) ? Html::encode(Yii::$app->formatter->asDatetime($throw['createdAt'], 'php:d/m/Y H:i:s')) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    </div>
</section>

<?php
$this->registerJs(<<<'JS'
(() => {
    const url = window.location.href;
    const selectors = ['#match-participants', '#match-throws'];
    let showAllThrows = false;
    function updateThrowsVisibility() {
        document.querySelectorAll('#match-throws [data-match-throw]').forEach((row, index) => {
            row.hidden = !showAllThrows && index >= 6;
        });
        const button = document.querySelector('#match-throws-toggle');
        if (button) {
            button.textContent = showAllThrows ? 'mostra ultimi' : 'mostra tutti';
            button.setAttribute('aria-expanded', String(showAllThrows));
        }
    }
    document.addEventListener('click', event => {
        if (!event.target.closest('#match-throws-toggle')) return;
        showAllThrows = !showAllThrows;
        updateThrowsVisibility();
    });
    updateThrowsVisibility();
    const isEditing = () => document.querySelector('#add-player-modal.show, #add-player-modal.showing') !== null;
    async function refresh() {
        try {
            if (document.hidden || isEditing()) return;
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                signal: AbortSignal.timeout(10000),
            });
            if (!response.ok || response.redirected) return;
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (document.hidden || isEditing()) return;
            const updates = selectors.map(selector => [document.querySelector(selector), page.querySelector(selector)]);
            if (updates.some(([current, next]) => !current || !next)) return;
            updates.forEach(([current, next]) => {
                if (current.innerHTML !== next.innerHTML) current.innerHTML = next.innerHTML;
            });
            updateThrowsVisibility();
        } catch (error) {
            // Keep the displayed data on transient failures and retry next time.
        } finally {
            window.setTimeout(refresh, 3000);
        }
    }
    window.setTimeout(refresh, 3000);
})();
JS);
?>