<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Game;
use app\models\DartsMatch;

final class DashboardController extends AdminController
{
    public function actionIndex(): string
    {
        $games = Game::findEnabled();
        $activeMatches = DartsMatch::findRecent(8, true);
        $recentMatches = DartsMatch::findRecent(8);

        return $this->render('index', compact('games', 'activeMatches', 'recentMatches'));
    }
}
