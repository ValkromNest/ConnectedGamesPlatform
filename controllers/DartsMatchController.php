<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\enums\MatchStatus;
use app\models\DartsMatch;
use app\models\Game;
use app\models\Device;
use app\models\MatchPlayer;
use app\services\DartsGameServiceClient;
use app\services\IdentityServiceClient;
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

            if ($model->saveDetails()) {
                Yii::$app->session->setFlash('success', 'Partita di freccette creata.');

                return $this->redirect(['index']);
            }
        }

        $devices = Device::availableForGame((int) $dartsGame->id);

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

            if ($model->saveDetails()) {
                Yii::$app->session->setFlash('success', 'Partita di freccette aggiornata.');

                return $this->redirect(['index']);
            }
        }

        $devices = Device::availableForGame((int) $model->game_id, $model->device_id === null ? null : (int) $model->device_id);

        return $this->render('update', compact('model', 'devices'));
    }

    public function actionView(int $id): string
    {
        $model = $this->findDartsMatch($id);
        $model->populateRelation('matchPlayers', $model->getOrderedPlayers());
        $availablePlayers = [];
        if ($model->status === MatchStatus::OPEN->value) {
            try {
                $assignedSubjects = array_map(
                    static fn (MatchPlayer $player): string => (string) $player->identity_subject_id,
                    $model->matchPlayers,
                );
                foreach ($this->identityService()->players($this->identityAccessToken()) as $player) {
                    if (!in_array($player['sub'], $assignedSubjects, true)) {
                        $availablePlayers[$player['sub']] = $player['username'];
                    }
                }
            } catch (\Throwable $exception) {
                Yii::warning('Could not load players from identity service: ' . $exception->getMessage(), __METHOD__);
                Yii::$app->session->setFlash('error', 'Impossibile caricare i player dal servizio identità. Verifica che sia attivo e riprova.');
            }
        }
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
            $model->assertCanAddPlayer();
            $subjectId = trim((string) $this->request->post('player_subject_id', ''));
            $user = null;
            foreach ($this->identityService()->players($this->identityAccessToken()) as $candidate) {
                if (hash_equals($candidate['sub'], $subjectId)) {
                    $user = $candidate;
                    break;
                }
            }
            if ($user === null) {
                throw new DomainException('Seleziona un player valido.');
            }
            $model->addPlayer($user['sub'], $user['username']);
            Yii::$app->session->setFlash('success', 'Player aggiunto alla partita.');
        } catch (DomainException $exception) {
            Yii::$app->session->setFlash('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            Yii::warning('Could not add player from identity service: ' . $exception->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Impossibile verificare il player con il servizio identità. Verifica che sia attivo e riprova.');
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionRemovePlayer(int $id, string $playerSubjectId): Response
    {
        $model = $this->findDartsMatch($id);
        try {
            $model->removePlayer($playerSubjectId);
            Yii::$app->session->setFlash('success', 'Player rimosso dalla partita.');
        } catch (DomainException $exception) {
            Yii::$app->session->setFlash('error', $exception->getMessage());
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

        $players = $model->getOrderedPlayers();
        $contextPlayers = array_map(static fn (MatchPlayer $player): array => [
            'playerId' => (string) $player->identity_subject_id,
            'displayName' => (string) $player->display_name,
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
        $model = DartsMatch::findById($id);

        if (!$model instanceof DartsMatch) {
            throw new NotFoundHttpException('Partita di freccette non trovata.');
        }

        return $model;
    }

    private function identityService(): IdentityServiceClient
    {
        return new IdentityServiceClient();
    }

    private function identityAccessToken(): string
    {
        $token = Yii::$app->session->get('identity_service_access_token');
        if (!is_string($token) || $token === '') {
            throw new \RuntimeException('Identity service session token is missing.');
        }
        return $token;
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

}
