<?php

declare(strict_types=1);

namespace app\controllers\api;

use app\enums\MatchStatus;
use app\models\Device;
use app\models\DartsMatch;
use app\models\MatchPlayer;
use app\services\DartsGameServiceClient;
use app\services\DartsGameServiceException;
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
        $devices = Device::findEnabledDarts();

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

        $device = Device::findEnabledDartsById((int) $payload['device_id']);
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

        $match = DartsMatch::findAssignedToDevice($device);
        if ($match === null) {
            return $this->error(404, 'Nessuna partita assegnata a questo dispositivo.');
        }

        try {
            return $this->matchState($match);
        } catch (DartsGameServiceException $exception) {
            return $this->error($exception->statusCode, $exception->getMessage());
        } catch (\Throwable $exception) {
            Yii::error($exception, __METHOD__);
            return $this->error(503, 'Darts game service unavailable.');
        }
    }

    /** Registra un tiro; identity e punteggio del player sono determinati dal server. */
    public function actionThrow(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $device = $this->authenticateDevice();
        if ($device === null) {
            return $this->error(401, 'Token dispositivo mancante o non valido.');
        }

        $match = DartsMatch::findAssignedToDevice($device);
        if ($match === null) {
            return $this->error(404, 'Nessuna partita assegnata a questo dispositivo.');
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
        $idempotencyKey = Yii::$app->request->headers->get('Idempotency-Key', '');
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $idempotencyKey)) {
            $idempotencyKey = self::newUuid();
        }
        try {
            $result = (new DartsGameServiceClient())->recordThrow(
                (int) $match->id,
                (int) $device->id,
                $sector,
                $multiplier,
                strtolower($idempotencyKey),
            );
            if (($result['result'] ?? null) === 'checkout' && $match->status === MatchStatus::RUNNING->value) {
                $match->transitionTo(MatchStatus::FINISHED);
            }
            $match->reloadState();
            return [
                'throw' => [
                    'id' => (int) ($result['throwId'] ?? 0),
                    'sector' => (int) $result['sector'],
                    'multiplier' => (int) $result['multiplier'],
                    'score' => (int) $result['score'],
                    'result' => (string) $result['result'],
                ],
                'match' => $this->adaptGameState($match, $result['state']),
            ];
        } catch (DartsGameServiceException $exception) {
            return $this->error($exception->statusCode, $exception->getMessage());
        } catch (\Throwable $exception) {
            Yii::error($exception, __METHOD__);
            return $this->error(503, 'Il servizio di gioco unavailable.');
        }
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

    /** @return array<string, mixed> */
    private function matchState(DartsMatch $match): array
    {
        if (in_array($match->status, [MatchStatus::RUNNING->value, MatchStatus::FINISHED->value], true)) {
            return $this->adaptGameState($match, (new DartsGameServiceClient())->state((int) $match->id));
        }

        $players = $match->getOrderedPlayers();
        return [
            'match' => [
                'id' => (int) $match->id,
                'name' => $match->name,
                'status' => MatchStatus::from((int) $match->status)->name,
                'startedAt' => $match->started_at,
                'endedAt' => $match->ended_at,
            ],
            'players' => array_map(fn (MatchPlayer $player): array => $this->playerData($player), $players),
            'turn' => ['player' => null, 'dartsRemaining' => null],
            'winner' => null,
        ];
    }

    /** @param array<string, mixed> $gameState
     *  @return array<string, mixed>
     */
    private function adaptGameState(DartsMatch $match, array $gameState): array
    {
        $players = array_map(static function (array $player): array {
            $id = (string) ($player['playerId'] ?? '');
            return [
                'id' => ctype_digit($id) ? (int) $id : $id,
                'username' => (string) ($player['displayName'] ?? ''),
                'remainingScore' => (int) ($player['remainingScore'] ?? 501),
            ];
        }, $gameState['players'] ?? []);
        $turn = $gameState['turn'] ?? null;
        $turnPlayer = null;
        if (is_array($turn) && isset($turn['playerId'])) {
            foreach ($players as $player) {
                if ((string) $player['id'] === (string) $turn['playerId']) {
                    $turnPlayer = $player;
                    break;
                }
            }
        }
        $winner = null;
        $winnerId = $gameState['winnerPlayerId'] ?? null;
        if ($winnerId !== null) {
            foreach ($players as $player) {
                if ((string) $player['id'] === (string) $winnerId) {
                    $winner = $player;
                    break;
                }
            }
        }
        return [
            'match' => [
                'id' => (int) $match->id,
                'name' => (string) $match->name,
                'status' => (string) ($gameState['status'] ?? MatchStatus::from((int) $match->status)->name),
                'startedAt' => $match->started_at,
                'endedAt' => $match->ended_at,
            ],
            'players' => $players,
            'turn' => [
                'player' => $turnPlayer,
                'dartsRemaining' => is_array($turn) ? ($turn['dartsRemaining'] ?? null) : null,
            ],
            'winner' => $winner,
        ];
    }

    /** @return array{id: string, username: string, remainingScore: int} */
    private function playerData(MatchPlayer $player): array
    {
        return [
            'id' => (string) $player->identity_subject_id,
            'username' => (string) $player->display_name,
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
