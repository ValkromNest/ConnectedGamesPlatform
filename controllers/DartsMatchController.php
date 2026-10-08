<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\enums\MatchStatus;
use app\models\DartsMatch;
use app\models\Game;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class DartsMatchController extends AdminController
{
    public function actionIndex(): string
    {
        $searchModel = new DartsMatch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', compact('dataProvider', 'searchModel'));
    }

    public function actionCreate(): Response|string
    {
        $dartsGame = $this->findDartsGame();
        $model = new DartsMatch([
            'game_id' => $dartsGame->id,
            'status' => MatchStatus::CREATED->value,
            'max_players' => 2,
        ]);

        if ($model->load($this->request->post())) {
            // Il gioco e lo stato sono definiti dal flusso amministrativo, non dal form.
            $model->game_id = $dartsGame->id;
            $model->status = MatchStatus::CREATED->value;

            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Partita di freccette creata.');

                return $this->redirect(['index']);
            }
        }

        return $this->render('create', compact('model'));
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findDartsMatch($id);
        $gameId = $model->game_id;
        $status = $model->status;

        if ($model->load($this->request->post())) {
            // Gioco e stato sono gestiti dal flusso della partita, non da questo form.
            $model->game_id = $gameId;
            $model->status = $status;

            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Partita di freccette aggiornata.');

                return $this->redirect(['index']);
            }
        }

        return $this->render('update', compact('model'));
    }

    private function findDartsGame(): Game
    {
        $game = DartsMatch::findEnabledGame();

        if ($game === null) {
            throw new NotFoundHttpException('Il gioco Freccette non è disponibile.');
        }

        return $game;
    }

    private function findDartsMatch(int $id): DartsMatch
    {
        $model = DartsMatch::findDarts()
            ->andWhere(['{{%tbl_matches}}.id' => $id])
            ->one();

        if (!$model instanceof DartsMatch) {
            throw new NotFoundHttpException('Partita di freccette non trovata.');
        }

        return $model;
    }
}
