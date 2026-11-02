<?php

declare(strict_types=1);

namespace darts\services;

use darts\DartsThrowCalculator;
use darts\exceptions\GameConflictException;
use darts\exceptions\GameNotFoundException;
use darts\models\GameMatch;
use darts\models\GamePlayer;
use darts\models\GameThrow;
use DomainException;
use Yii;
use yii\db\Transaction;

final class DartsGameService
{
    public function __construct(private readonly DartsThrowCalculator $calculator = new DartsThrowCalculator())
    {
    }

    /** @param array<string, mixed> $context */
    public function initialize(string $matchId, array $context, string $idempotencyKey, string $correlationId): array
    {
        $players = $this->validateContext($context);
        $canonical = [
            'contextVersion' => 1,
            'gameCode' => 'darts',
            'players' => $players,
            'configuration' => ['startingScore' => 501, 'checkout' => 'double'],
        ];
        $contextHash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
        $transaction = Yii::$app->db->beginTransaction(Transaction::SERIALIZABLE);
        try {
            $existing = GameMatch::findOne($matchId);
            if ($existing !== null) {
                if (!hash_equals((string) $existing->context_hash, $contextHash)) {
                    throw new GameConflictException('Match was already initialized with a different context.');
                }
                $transaction->commit();
                return $this->state($matchId, $correlationId);
            }

            $match = new GameMatch([
                'match_id' => $matchId,
                'status' => 'RUNNING',
                'starting_score' => DartsThrowCalculator::STARTING_SCORE,
                'context_hash' => $contextHash,
                'context_request_key' => $idempotencyKey,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if (!$match->save()) {
                throw new DomainException('Could not initialize game match.');
            }
            foreach ($players as $playerData) {
                $player = new GamePlayer([
                    'match_id' => $matchId,
                    'player_id' => $playerData['playerId'],
                    'display_name' => $playerData['displayName'],
                    'turn_order' => $playerData['turnOrder'],
                    'remaining_score' => DartsThrowCalculator::STARTING_SCORE,
                ]);
                if (!$player->save()) {
                    throw new DomainException('Could not initialize game players.');
                }
            }
            $transaction->commit();
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }

        return $this->state($matchId, $correlationId);
    }

    /** @return array<string, mixed> */
    public function state(string $matchId, string $correlationId): array
    {
        $match = GameMatch::findOne($matchId);
        if ($match === null) {
            throw new GameNotFoundException('Game match not found.');
        }
        $players = GamePlayer::find()
            ->where(['match_id' => $matchId])
            ->orderBy(['turn_order' => SORT_ASC])
            ->all();
        $throws = GameThrow::find()
            ->where(['match_id' => $matchId])
            ->orderBy(['id' => SORT_ASC])
            ->all();
        $replay = $this->replay($match, $players, $throws);

        return [
            'matchId' => $matchId,
            'status' => $match->status,
            'players' => array_map(static fn (GamePlayer $player): array => [
                'playerId' => (string) $player->player_id,
                'displayName' => (string) $player->display_name,
                'remainingScore' => (int) $player->remaining_score,
                'turnOrder' => (int) $player->turn_order,
            ], $players),
            'throws' => array_map(static fn (GameThrow $throw): array => [
                'playerId' => (string) $throw->player_id,
                'sector' => (int) $throw->sector,
                'multiplier' => (int) $throw->multiplier,
                'score' => (int) $throw->score,
                'result' => (string) $throw->result,
                'createdAt' => (string) $throw->created_at,
            ], $throws),
            'turn' => $replay['currentPlayer'] === null ? null : [
                'playerId' => (string) $replay['currentPlayer']->player_id,
                'dartsRemaining' => 3 - $replay['dartsInVisit'],
            ],
            'winnerPlayerId' => $replay['winnerPlayerId'],
            'correlationId' => $correlationId,
        ];
    }

    public function finish(string $matchId, string $correlationId): array
    {
        $match = GameMatch::findOne($matchId);
        if ($match === null) {
            throw new GameNotFoundException('Game match not found.');
        }
        if ($match->status === 'RUNNING') {
            $match->status = 'FINISHED';
            $match->finished_at = date('Y-m-d H:i:s');
            if (!$match->save(false, ['status', 'finished_at'])) {
                throw new DomainException('Could not finish game match.');
            }
        }
        return $this->state($matchId, $correlationId);
    }

    /** @return array<string, mixed> */
    public function recordThrow(
        string $matchId,
        string $deviceId,
        int $sector,
        int $multiplier,
        string $idempotencyKey,
        string $correlationId,
    ): array {
        $score = $this->calculator->calculate($sector, $multiplier);
        $requestHash = hash('sha256', json_encode([
            'deviceId' => $deviceId,
            'sector' => $sector,
            'multiplier' => $multiplier,
        ], JSON_THROW_ON_ERROR));
        $transaction = Yii::$app->db->beginTransaction(Transaction::READ_COMMITTED);
        try {
            $lockedMatchId = Yii::$app->db->createCommand(
                'SELECT [[match_id]] FROM {{%darts_game_match}} WHERE [[match_id]] = :matchId FOR UPDATE',
                [':matchId' => $matchId],
            )->queryScalar();
            if ($lockedMatchId === false) {
                throw new GameNotFoundException('Game match not found.');
            }
            $match = GameMatch::findOne($matchId);
            $prior = GameThrow::findOne([
                'match_id' => $matchId,
                'idempotency_key' => $idempotencyKey,
            ]);
            if ($prior !== null) {
                if (!hash_equals((string) $prior->request_hash, $requestHash)) {
                    throw new GameConflictException('Idempotency key was already used with a different request.');
                }
                $transaction->commit();
                if (!is_string($prior->response_json) || $prior->response_json === '') {
                    throw new DomainException('Stored idempotent response is missing.');
                }
                return json_decode($prior->response_json, true, 512, JSON_THROW_ON_ERROR);
            }
            if ($match->status !== 'RUNNING') {
                throw new GameConflictException('Throws can only be registered while the game is running.');
            }

            $players = GamePlayer::find()
                ->where(['match_id' => $matchId])
                ->orderBy(['turn_order' => SORT_ASC])
                ->all();
            $throws = GameThrow::find()
                ->where(['match_id' => $matchId])
                ->orderBy(['id' => SORT_ASC])
                ->all();
            $replay = $this->replay($match, $players, $throws);
            $currentPlayer = $replay['currentPlayer'];
            if (!$currentPlayer instanceof GamePlayer) {
                throw new GameConflictException('There is no active player.');
            }

            $remaining = $replay['remaining'][(string) $currentPlayer->player_id];
            $newRemaining = $remaining - $score;
            $isDouble = $multiplier === 2;
            $isBust = $newRemaining < 0 || $newRemaining === 1 || ($newRemaining === 0 && !$isDouble);
            $isCheckout = $newRemaining === 0 && $isDouble;
            $result = $isCheckout ? 'checkout' : ($isBust ? 'bust' : ($sector === 0 ? 'missed' : 'scored'));

            $throw = new GameThrow([
                'match_id' => $matchId,
                'player_id' => $currentPlayer->player_id,
                'device_id' => $deviceId,
                'sector' => $sector,
                'multiplier' => $multiplier,
                'score' => $score,
                'result' => $result,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if (!$throw->save()) {
                throw new DomainException('Could not persist dart throw.');
            }

            $currentPlayer->remaining_score = $isBust ? $replay['visitStartScore'] : $newRemaining;
            if (!$currentPlayer->save(false, ['remaining_score'])) {
                throw new DomainException('Could not update player score.');
            }
            if ($isCheckout) {
                $match->status = 'FINISHED';
                $match->finished_at = date('Y-m-d H:i:s');
                if (!$match->save(false, ['status', 'finished_at'])) {
                    throw new DomainException('Could not finish game match.');
                }
            }

            $response = [
                'throwId' => (string) $throw->id,
                'sector' => $sector,
                'multiplier' => $multiplier,
                'score' => $score,
                'result' => $result,
                'state' => $this->state($matchId, $correlationId),
            ];
            $throw->response_json = json_encode($response, JSON_THROW_ON_ERROR);
            if (!$throw->save(false, ['response_json'])) {
                throw new DomainException('Could not persist idempotent response.');
            }
            $transaction->commit();
            return $response;
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $context
     *  @return list<array{playerId: string, displayName: string, turnOrder: int}>
     */
    private function validateContext(array $context): array
    {
        if (($context['contextVersion'] ?? null) !== 1 || ($context['gameCode'] ?? null) !== 'darts') {
            throw new \InvalidArgumentException('Unsupported game context.');
        }
        $configuration = $context['configuration'] ?? null;
        if (!is_array($configuration)
            || ($configuration['startingScore'] ?? null) !== DartsThrowCalculator::STARTING_SCORE
            || ($configuration['checkout'] ?? null) !== 'double'
        ) {
            throw new \InvalidArgumentException('Darts configuration must be 501 with double checkout.');
        }
        $sourcePlayers = $context['players'] ?? null;
        if (!is_array($sourcePlayers) || count($sourcePlayers) < 1) {
            throw new \InvalidArgumentException('At least one player is required.');
        }
        $players = [];
        $ids = [];
        $orders = [];
        foreach ($sourcePlayers as $player) {
            if (!is_array($player)
                || !is_string($player['playerId'] ?? null)
                || $player['playerId'] === ''
                || strlen($player['playerId']) > 64
                || !is_string($player['displayName'] ?? null)
                || trim($player['displayName']) === ''
                || strlen($player['displayName']) > 120
                || filter_var($player['turnOrder'] ?? null, FILTER_VALIDATE_INT) === false
                || (int) $player['turnOrder'] < 1
            ) {
                throw new \InvalidArgumentException('Player context is invalid.');
            }
            $id = $player['playerId'];
            $order = (int) $player['turnOrder'];
            if (isset($ids[$id]) || isset($orders[$order])) {
                throw new \InvalidArgumentException('Player IDs and turn orders must be unique.');
            }
            $ids[$id] = true;
            $orders[$order] = true;
            $players[] = [
                'playerId' => $id,
                'displayName' => trim($player['displayName']),
                'turnOrder' => $order,
            ];
        }
        usort($players, static fn (array $a, array $b): int => $a['turnOrder'] <=> $b['turnOrder']);
        return $players;
    }

    /** @param list<GamePlayer> $players
     *  @param list<GameThrow> $throws
     *  @return array{remaining: array<string, int>, currentPlayer: ?GamePlayer, dartsInVisit: int, visitStartScore: int, winnerPlayerId: ?string}
     */
    private function replay(GameMatch $match, array $players, array $throws): array
    {
        if ($players === []) {
            throw new DomainException('Initialized game has no players.');
        }
        $remaining = [];
        foreach ($players as $player) {
            $remaining[(string) $player->player_id] = (int) $match->starting_score;
        }
        $currentIndex = 0;
        $dartsInVisit = 0;
        $visitStartScore = (int) $match->starting_score;
        $winnerPlayerId = null;
        foreach ($throws as $throw) {
            if ($winnerPlayerId !== null) {
                throw new DomainException('Game contains throws after checkout.');
            }
            $player = $players[$currentIndex];
            if ((string) $throw->player_id !== (string) $player->player_id) {
                throw new DomainException('Persisted throw order is inconsistent.');
            }
            $calculated = $this->calculator->calculate((int) $throw->sector, (int) $throw->multiplier);
            if ((int) $throw->score !== $calculated) {
                throw new DomainException('Persisted throw score is inconsistent.');
            }
            $playerId = (string) $player->player_id;
            $next = $remaining[$playerId] - $calculated;
            $isDouble = (int) $throw->multiplier === 2;
            $isBust = $next < 0 || $next === 1 || ($next === 0 && !$isDouble);
            if ($isBust) {
                $remaining[$playerId] = $visitStartScore;
                $currentIndex = ($currentIndex + 1) % count($players);
                $dartsInVisit = 0;
                $visitStartScore = $remaining[(string) $players[$currentIndex]->player_id];
                continue;
            }
            $remaining[$playerId] = $next;
            if ($next === 0 && $isDouble) {
                $winnerPlayerId = $playerId;
                continue;
            }
            $dartsInVisit++;
            if ($dartsInVisit === 3) {
                $currentIndex = ($currentIndex + 1) % count($players);
                $dartsInVisit = 0;
                $visitStartScore = $remaining[(string) $players[$currentIndex]->player_id];
            }
        }
        foreach ($players as $player) {
            $playerId = (string) $player->player_id;
            if ((int) $player->remaining_score !== $remaining[$playerId]) {
                throw new DomainException('Stored score does not match replayed throws.');
            }
        }
        if ($winnerPlayerId !== null && $match->status !== 'FINISHED') {
            throw new DomainException('Game status does not match its throws.');
        }
        return [
            'remaining' => $remaining,
            'currentPlayer' => $winnerPlayerId === null && $match->status === 'RUNNING' ? $players[$currentIndex] : null,
            'dartsInVisit' => $dartsInVisit,
            'visitStartScore' => $visitStartScore,
            'winnerPlayerId' => $winnerPlayerId,
        ];
    }
}
