<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\enums\MatchStatus;
use app\models\DartsMatch;
use app\models\Game;
use app\models\Device;
use app\models\User;
use app\models\MatchPlayer;
use app\services\DartsGameServiceClient;
use DomainException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\filters\VerbFilter;

final class DartsMatchController extends AdminController
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => ['class' => VerbFilter::class, 'actions' => [
                'open' => ['POST'], 'start' => ['POST'], 'finish' => ['POST'],
                'add-player' => ['POST'], 'remove-player' => ['POST'],
            ]],
        ]);
    }

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

        $devices = $this->availableDevices((int) $dartsGame->id);

        return $this->render('create', compact('model', 'devices'));
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findDartsMatch($id);
        if (in_array((int) $model->status, [MatchStatus::RUNNING->value, MatchStatus::FINISHED->value], true)) {
            Yii::$app->session->setFlash('error', 'Una partita avviata non può essere modificata.');
            return $this->redirect(['view', 'id' => $id]);
        }
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

        $devices = $this->availableDevices((int) $model->game_id, $model->device_id === null ? null : (int) $model->device_id);

        return $this->render('update', compact('model', 'devices'));
    }

    public function actionView(int $id): string
    {
        $model = $this->findDartsMatch($id);
        $model->populateRelation('matchPlayers', $model->getMatchPlayers()->with('user')->orderBy(['turn_order' => SORT_ASC, 'id' => SORT_ASC])->all());
        $assignedUserIds = array_map(static fn (MatchPlayer $player): int => (int) $player->user_id, $model->matchPlayers);
        $availablePlayers = User::find()
            ->select('username')
            ->where(['role' => 'player'])
            ->andFilterWhere(['not in', 'id', $assignedUserIds])
            ->orderBy(['username' => SORT_ASC])
            ->indexBy('id')
            ->column();
        $gameState = null;
        if (in_array((int) $model->status, [MatchStatus::RUNNING->value, MatchStatus::FINISHED->value], true)) {
            try {
                $gameState = (new DartsGameServiceClient())->state((int) $model->id);
            } catch (\Throwable $exception) {
                Yii::error($exception, __METHOD__);
                Yii::$app->session->setFlash('error', 'Impossibile leggere lo stato dal servizio Freccette: ' . $exception->getMessage());
            }
        }

        return $this->render('view', compact('model', 'availablePlayers', 'gameState'));
    }

    public function actionAddPlayer(int $id): Response
    {
        $model = $this->findDartsMatch($id);
        try {
            if ($model->status !== MatchStatus::OPEN->value) {
                throw new DomainException('I partecipanti si possono modificare solo prima dell’avvio.');
            }
            if ((int) MatchPlayer::find()->where(['match_id' => $id])->count() >= (int) $model->max_players) {
                throw new DomainException('La partita ha già raggiunto il numero massimo di partecipanti.');
            }
            $userId = (int) $this->request->post('user_id');
            $user = User::find()->where(['id' => $userId, 'role' => 'player'])->one();
            if ($user === null) {
                throw new DomainException('Seleziona un player valido.');
            }
            $participant = new MatchPlayer([
                'match_id' => $id,
                'user_id' => $user->id,
                'score' => 501,
                'turn_order' => (int) MatchPlayer::find()->where(['match_id' => $id])->count() + 1,
            ]);
            if (!$participant->save()) {
                throw new DomainException('Impossibile aggiungere il player alla partita.');
            }
            Yii::$app->session->setFlash('success', 'Player aggiunto alla partita.');
        } catch (DomainException $exception) {
            Yii::$app->session->setFlash('error', $exception->getMessage());
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionRemovePlayer(int $id, int $userId): Response
    {
        $model = $this->findDartsMatch($id);
        if ($model->status !== MatchStatus::OPEN->value) {
            Yii::$app->session->setFlash('error', 'È possibile rimuovere player solo da una partita aperta.');
            return $this->redirect(['view', 'id' => $id]);
        }

        $participant = MatchPlayer::findOne(['match_id' => $id, 'user_id' => $userId]);
        if ($participant === null) {
            Yii::$app->session->setFlash('error', 'Player non trovato tra i partecipanti.');
            return $this->redirect(['view', 'id' => $id]);
        }

        if ($participant->delete() !== false) {
            $remaining = MatchPlayer::find()->where(['match_id' => $id])->orderBy(['turn_order' => SORT_ASC, 'id' => SORT_ASC])->all();
            foreach ($remaining as $index => $player) {
                $player->updateAttributes(['turn_order' => $index + 1]);
            }
            Yii::$app->session->setFlash('success', 'Player rimosso dalla partita.');
        } else {
            Yii::$app->session->setFlash('error', 'Impossibile rimuovere il player.');
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionOpen(int $id): Response
    {
        return $this->transition($id, MatchStatus::OPEN, 'Partita aperta.');
    }

    public function actionStart(int $id): Response
    {
        $model = $this->findDartsMatch($id);
        if ($model->status !== MatchStatus::OPEN->value) {
            Yii::$app->session->setFlash('error', 'È possibile avviare solo una partita aperta.');
            return $this->redirect(['view', 'id' => $id]);
        }

        $players = MatchPlayer::find()
            ->with('user')
            ->where(['match_id' => $id])
            ->orderBy(['turn_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
        $contextPlayers = array_map(static fn (MatchPlayer $player): array => [
            'playerId' => (string) $player->user_id,
            'displayName' => (string) ($player->user?->username ?? ''),
            'turnOrder' => (int) $player->turn_order,
        ], $players);

        try {
            $initialized = (new DartsGameServiceClient())->initialize(
                $id,
                $contextPlayers,
                self::contextIdempotencyKey($id),
            );
            if (($initialized['status'] ?? null) !== 'RUNNING') {
                throw new \RuntimeException('Il servizio di gioco non ha avviato la partita.');
            }
            if (!$model->transitionTo(MatchStatus::RUNNING)) {
                throw new \RuntimeException('Il servizio di gioco è pronto, ma lo stato della partita non è stato aggiornato. Riprova l’avvio.');
            }
            Yii::$app->session->setFlash('success', 'Partita avviata.');
        } catch (\Throwable $exception) {
            Yii::error($exception, __METHOD__);
            Yii::$app->session->setFlash('error', 'Impossibile avviare il gioco: ' . $exception->getMessage());
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionFinish(int $id): Response
    {
        $model = $this->findDartsMatch($id);
        if ($model->status === MatchStatus::RUNNING->value) {
            try {
                (new DartsGameServiceClient())->finish($id);
                if (!$model->transitionTo(MatchStatus::FINISHED)) {
                    throw new \RuntimeException('Il gioco è stato terminato dal servizio, ma lo stato generale non è stato aggiornato.');
                }
                Yii::$app->session->setFlash('success', 'Partita terminata.');
            } catch (\Throwable $exception) {
                Yii::error($exception, __METHOD__);
                Yii::$app->session->setFlash('error', 'Impossibile terminare la partita tramite il servizio Freccette: ' . $exception->getMessage());
            }
            return $this->redirect(['view', 'id' => $id]);
        }
        return $this->transition($id, MatchStatus::FINISHED, 'Partita terminata.');
    }

    private function transition(int $id, MatchStatus $status, string $successMessage): Response
    {
        $model = $this->findDartsMatch($id);
        if ($model->transitionTo($status)) {
            Yii::$app->session->setFlash('success', $successMessage);
        } else {
            Yii::$app->session->setFlash('error', 'Transizione non consentita: aggiorna la pagina e verifica lo stato della partita.');
        }

        return $this->redirect(['view', 'id' => $id]);
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

    private static function contextIdempotencyKey(int $matchId): string
    {
        $hex = substr(hash('sha256', 'darts-match-context:' . $matchId), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    /** @return array<int, string> */
    private function availableDevices(int $gameId, ?int $currentDeviceId = null): array
    {
        $query = Device::find()
            ->select('name')
            ->where(['game_id' => $gameId]);

        if ($currentDeviceId === null) {
            $query->andWhere(['is_enabled' => true]);
        } else {
            $query->andWhere(['or', ['is_enabled' => true], ['id' => $currentDeviceId]]);
        }

        return $query->indexBy('id')->column();
    }
}
