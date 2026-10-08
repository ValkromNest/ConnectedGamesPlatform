<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var app\models\Game[] $games */
/** @var app\models\DartsMatch[] $activeMatches */
/** @var app\models\DartsMatch[] $recentMatches */

use app\enums\MatchStatus;
use app\models\DartsMatch;
use yii\helpers\Html;

$this->title = 'Dashboard';
$this->params['breadcrumbs'][] = $this->title;
$renderMatches = static function (array $matches): string {
    if ($matches === []) {
        return '<p class="text-body-secondary mb-0">Nessuna partita da mostrare.</p>';
    }
    $rows = '';
    foreach ($matches as $match) {
        $rows .= '<tr><td>' . Html::a(Html::encode($match->name), ['/darts-match/view', 'id' => $match->id]) . '</td><td>'
            . Html::encode(MatchStatus::from($match->status)->label()) . '</td><td>'
            . Yii::$app->formatter->asDatetime($match->created_at) . '</td></tr>';
    }
    return '<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Partita</th><th>Stato</th><th>Creata il</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
};
?>
<section class="dashboard py-5">
    <h1 class="h2 mb-2">Dashboard</h1>
    <p class="text-body-secondary mb-4">Benvenuto, <?= Html::encode(Yii::$app->user->identity->username) ?>.</p>

    <div class="card shadow-sm mb-4"><div class="card-body">
        <h2 class="h5">Giochi disponibili</h2>
        <?php if ($games === []): ?>
            <p class="text-body-secondary mb-0">Nessun gioco abilitato.</p>
        <?php else: ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($games as $game): ?>
                    <span class="badge text-bg-light border fs-6"><?= Html::encode($game->name) ?></span>
                <?php endforeach; ?>
                <?= Html::a('Gestisci partite freccette', ['/darts-match/index'], ['class' => 'btn btn-sm btn-outline-primary']) ?>
            </div>
        <?php endif; ?>
    </div></div>

    <div class="card shadow-sm mb-4"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Partite attive</h2><?= Html::a('Apri elenco', ['/darts-match/index'], ['class' => 'btn btn-sm btn-outline-secondary']) ?></div>
        <?= $renderMatches($activeMatches) ?>
    </div></div>

    <div class="card shadow-sm"><div class="card-body">
        <h2 class="h5 mb-3">Partite recenti</h2>
        <?= $renderMatches($recentMatches) ?>
    </div></div>
</section>
