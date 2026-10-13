<?php

declare(strict_types=1);

namespace app\controllers\api;

use app\enums\MatchStatus;
use app\models\Device;
use app\models\DartsMatch;
use app\models\MatchPlayer;
use app\services\DartsGameService;
use app\services\DartsThrowCalculator;
use DomainException;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/** API HTTP per il simulatore/dispositivo del gioco Freccette. */
final class DartsApiController extends Controller
{
    // Le richieste API usano Bearer token e non cookie di sessione/form HTML.
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'devices' => ['GET'],
                    'session' => ['POST'],
                    'state' => ['GET'],
                    'throw' => ['POST'],
                ],
            ],
        ];
    }

    /** Dispositivi Freccette abilitati disponibili per la selezione nel simulatore. */
    public function actionDevices(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $devices = Device::find()
            ->joinWith('game')
            ->where(['{{%tbl_device}}.is_enabled' => true, '{{%tbl_game}}.code' => DartsMatch::GAME_CODE])
            ->orderBy(['{{%tbl_device}}.name' => SORT_ASC])
            ->all();

        return ['devices' => array_map(static fn (Device $device): array => [
            'id' => (int) $device->id,
            'name' => $device->name,
        ], $devices)];
    }

    /** Restituisce il token del dispositivo scelto, mantenendolo solo nella sessione del simulatore. */
    public function actionSession(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $payload = json_decode(Yii::$app->request->getRawBody(), true);
        if (!is_array($payload) || !isset($payload['device_id'])
            || filter_var($payload['device_id'], FILTER_VALIDATE_INT) === false
        ) {
            return $this->error(400, 'Il JSON deve contenere device_id intero.');
        }

        $device = Device::find()
            ->joinWith('game')
            ->where([
                '{{%tbl_device}}.id' => (int) $payload['device_id'],
                '{{%tbl_device}}.is_enabled' => true,
                '{{%tbl_game}}.code' => DartsMatch::GAME_CODE,
            ])
            ->one();
        if ($device === null) {
            return $this->error(404, 'Dispositivo Freccette non trovato o disabilitato.');
        }

        $token = $this->decryptDeviceToken($device);
        if ($token === null) {
            return $this->error(422, 'Token non disponibile per questo dispositivo.');
        }

        return [
            'device' => ['id' => (int) $device->id, 'name' => $device->name],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    /** Stato corrente: il dispositivo interroga questo endpoint per rilevare avvio e termine. */
    public function actionState(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $device = $this->authenticateDevice();
        if ($device === null) {
            return $this->error(401, 'Token dispositivo mancante o non valido.');
        }

        $match = $this->assignedMatch($device);
        if ($match === null) {
            return $this->error(404, 'Nessuna partita è assegnata a questo dispositivo.');
        }

        return $this->matchState($match);
    }

    /** Registra un tiro; identità e punteggio del player sono determinati dal server. */
    public function actionThrow(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $device = $this->authenticateDevice();
        if ($device === null) {
            return $this->error(401, 'Token dispositivo mancante o non valido.');
        }

        $match = $this->assignedMatch($device);
        if ($match === null) {
            return $this->error(404, 'Nessuna partita è assegnata a questo dispositivo.');
        }

        $payload = json_decode(Yii::$app->request->getRawBody(), true);
        if (!is_array($payload) || !isset($payload['sector'], $payload['multiplier'])
            || filter_var($payload['sector'], FILTER_VALIDATE_INT) === false
            || filter_var($payload['multiplier'], FILTER_VALIDATE_INT) === false
        ) {
            return $this->error(400, 'Il JSON deve contenere sector e multiplier interi.');
        }

        $sector = (int) $payload['sector'];
        $multiplier = (int) $payload['multiplier'];
        $calculator = new DartsThrowCalculator();
        try {
            $score = $calculator->calculate($sector, $multiplier);
        } catch (DomainException $exception) {
            return $this->error(400, $exception->getMessage());
        }

        try {
            $currentPlayer = (new DartsGameService())->getCurrentPlayer((int) $match->id);
            $scoreBefore = (int) $currentPlayer->score;
            $newScore = $scoreBefore - $score;
            $isBust = $newScore < 0 || $newScore === 1 || ($newScore === 0 && $multiplier !== 2);
            $throw = (new DartsGameService())->recordThrow(
                (int) $match->id,
                (int) $currentPlayer->user_id,
                $sector,
                $multiplier,
            );
        } catch (DomainException $exception) {
            return $this->error(409, $exception->getMessage());
        }

        $match->refresh();
        $currentPlayer->refresh();
        $result = $match->status === MatchStatus::FINISHED->value
            ? 'checkout'
            : ($isBust ? 'bust' : ($sector === 0 ? 'missed' : 'scored'));

        return [
            'throw' => [
                'id' => (int) $throw->id,
                'sector' => $sector,
                'multiplier' => $multiplier,
                'score' => $score,
                'result' => $result,
            ],
            'match' => $this->matchState($match),
        ];
    }

    private function authenticateDevice(): ?Device
    {
        $authorization = Yii::$app->request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            return null;
        }

        $device = Device::findEnabledByToken(trim($matches[1]));
        if ($device === null || $device->game?->code !== DartsMatch::GAME_CODE) {
            return null;
        }

        return $device;
    }

    private function decryptDeviceToken(Device $device): ?string
    {
        if ($device->token_encrypted === null || $device->token_encrypted === '') {
            return null;
        }

        $key = Yii::$app->request->cookieValidationKey;
        $token = Yii::$app->security->decryptByKey($device->token_encrypted, $key);
        if ($token === false) {
            $encryptedToken = base64_decode($device->token_encrypted, true);
            $token = $encryptedToken === false ? false : Yii::$app->security->decryptByKey($encryptedToken, $key);
        }

        return is_string($token) ? $token : null;
    }

    private function assignedMatch(Device $device): ?DartsMatch
    {
        $active = DartsMatch::findDarts()
            ->where(['{{%tbl_matches}}.device_id' => $device->id])
            ->andWhere(['{{%tbl_matches}}.status' => [
                MatchStatus::CREATED->value,
                MatchStatus::OPEN->value,
                MatchStatus::RUNNING->value,
            ]])
            ->orderBy(['{{%tbl_matches}}.id' => SORT_DESC])
            ->one();
        if ($active !== null) {
            return $active;
        }

        return DartsMatch::findDarts()
            ->where(['{{%tbl_matches}}.device_id' => $device->id])
            ->orderBy(['{{%tbl_matches}}.id' => SORT_DESC])
            ->one();
    }

    /** @return array<string, mixed> */
    private function matchState(DartsMatch $match): array
    {
        $players = MatchPlayer::find()
            ->with('user')
            ->where(['match_id' => $match->id])
            ->orderBy(['turn_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
        $turnPlayer = null;
        $dartsRemaining = null;
        if ($match->status === MatchStatus::RUNNING->value) {
            try {
                $turnState = (new DartsGameService())->getTurnState((int) $match->id);
                $turnPlayer = $turnState['player'];
                $dartsRemaining = $turnState['dartsRemaining'];
            } catch (DomainException) {
                // Partita running senza player: il turno resta null e il client può mostrare lo stato incompleto.
            }
        }

        $winner = null;
        if ($match->status === MatchStatus::FINISHED->value) {
            foreach ($players as $player) {
                if ((int) $player->score === 0) {
                    $winner = $this->playerData($player);
                    break;
                }
            }
        }

        return [
            'match' => [
                'id' => (int) $match->id,
                'name' => $match->name,
                'status' => MatchStatus::from((int) $match->status)->name,
                'startedAt' => $match->started_at,
                'endedAt' => $match->ended_at,
            ],
            'players' => array_map(fn (MatchPlayer $player): array => $this->playerData($player), $players),
            'turn' => [
                'player' => $turnPlayer instanceof MatchPlayer ? $this->playerData($turnPlayer) : null,
                'dartsRemaining' => $dartsRemaining,
            ],
            'winner' => $winner,
        ];
    }

    /** @return array{id: int, username: string, remainingScore: int} */
    private function playerData(MatchPlayer $player): array
    {
        return [
            'id' => (int) $player->user_id,
            'username' => (string) ($player->user?->username ?? ''),
            'remainingScore' => (int) $player->score,
        ];
    }

    /** @return array{error: string} */
    private function error(int $status, string $message): array
    {
        Yii::$app->response->statusCode = $status;
        return ['error' => $message];
    }
}
