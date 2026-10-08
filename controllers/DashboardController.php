<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Game;
use app\models\DartsMatch;
use app\enums\MatchStatus;

final class DashboardController extends AdminController
{
    public function actionIndex(): string
    {
        $games = Game::find()->where(['enabled' => true])->orderBy(['name' => SORT_ASC])->all();
        $activeMatches = DartsMatch::findDarts()
            ->andWhere(['{{%tbl_matches}}.status' => [MatchStatus::CREATED->value, MatchStatus::OPEN->value, MatchStatus::RUNNING->value]])
            ->orderBy(['{{%tbl_matches}}.created_at' => SORT_DESC, '{{%tbl_matches}}.id' => SORT_DESC])
            ->limit(8)->all();
        $recentMatches = DartsMatch::findDarts()
            ->orderBy(['{{%tbl_matches}}.created_at' => SORT_DESC, '{{%tbl_matches}}.id' => SORT_DESC])
            ->limit(8)->all();

        return $this->render('index', compact('games', 'activeMatches', 'recentMatches'));
    }
}
