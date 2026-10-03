<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\enums\MatchStatus;
use app\models\Game;
use app\models\GameMatch;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class DartsMatchController extends AdminController
{
    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query' => GameMatch::find()
                ->innerJoinWith('game')
                ->andWhere(['{{%tbl_game}}.code' => 'darts'])
                ->orderBy(['{{%tbl_matches}}.created_at' => SORT_DESC, '{{%tbl_matches}}.id' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return $this->render('index', compact('dataProvider'));
    }

    public function actionCreate(): Response|string
    {
        $dartsGame = $this->findDartsGame();
        $model = new GameMatch([
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

    private function findDartsGame(): Game
    {
        $game = Game::find()->where(['code' => 'darts'])->andWhere(['enabled' => 1])->one();

        if ($game === null) {
            throw new NotFoundHttpException('Il gioco Freccette non è disponibile.');
        }

        return $game;
    }
}
