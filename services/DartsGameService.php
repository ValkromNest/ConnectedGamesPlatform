<?php

declare(strict_types=1);

namespace app\services;

use app\enums\MatchStatus;
use app\models\DartThrow;
use app\models\DartsMatch;
use app\models\MatchPlayer;
use app\models\User;
use DomainException;
use Yii;
use yii\db\ActiveQuery;

/** Regole applicative per partecipanti e tiri di una partita di Freccette. */
final class DartsGameService
{
    public function __construct(private readonly DartsThrowCalculator $calculator = new DartsThrowCalculator())
    {
    }

    public function addPlayer(int $matchId, string $username): MatchPlayer
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $match = $this->lockMatch($matchId);
            if ($match->status !== MatchStatus::OPEN->value) {
                throw new DomainException('È possibile aggiungere player solo a una partita aperta.');
            }

            $user = User::find()->where(['username' => $username, 'role' => 'player'])->one();
            if ($user === null) {
                throw new DomainException("Player '{$username}' non trovato.");
            }

            $players = $this->playersQuery($matchId)->all();
            if (count($players) >= $match->max_players) {
                throw new DomainException('La partita ha già raggiunto il numero massimo di player.');
            }
            foreach ($players as $player) {
                if ((int) $player->user_id === (int) $user->id) {
                    throw new DomainException('Il player partecipa già a questa partita.');
                }
            }

            $orders = array_map(static fn (MatchPlayer $player): int => (int) $player->turn_order, $players);
            $participant = new MatchPlayer([
                'match_id' => $matchId,
                'user_id' => $user->id,
                'score' => DartsThrowCalculator::STARTING_SCORE,
                'turn_order' => $orders === [] ? 1 : max($orders) + 1,
            ]);
            if (!$participant->save()) {
                throw new DomainException('Impossibile aggiungere il player: ' . implode(' ', $participant->getFirstErrors()));
            }

            $transaction->commit();
            return $participant;
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }
    }

    /** Restituisce il player che deve effettuare il prossimo tiro. */
    public function getCurrentPlayer(int $matchId): MatchPlayer
    {
        return $this->getTurnState($matchId)['player'];
    }

    /** @return array{player: MatchPlayer, dartsRemaining: int} */
    public function getTurnState(int $matchId): array
    {
        $match = DartsMatch::findDarts()->andWhere(['{{%tbl_matches}}.id' => $matchId])->one();
        if ($match === null) {
            throw new DomainException('Partita di Freccette non trovata.');
        }
        if ($match->status !== MatchStatus::RUNNING->value) {
            throw new DomainException('La partita non è in corso.');
        }

        $players = $this->playersQuery($matchId)->all();
        $state = $this->replay($players, $this->throwsQuery($matchId)->all());
        return [
            'player' => $players[$state['currentIndex']],
            'dartsRemaining' => 3 - $state['dartsInVisit'],
        ];
    }

    /** Registra il tiro, aggiorna il punteggio e fa avanzare il turno atomicamente. */
    public function recordThrow(int $matchId, int $userId, int $sector, int $multiplier): DartThrow
    {
        $score = $this->calculator->calculate($sector, $multiplier);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $match = $this->lockMatch($matchId);
            if ($match->status !== MatchStatus::RUNNING->value) {
                throw new DomainException('Si possono registrare tiri solo per partite in corso.');
            }

            $players = $this->playersQuery($matchId)->all();
            $state = $this->replay($players, $this->throwsQuery($matchId)->all());
            $currentPlayer = $players[$state['currentIndex']];
            if ((int) $currentPlayer->user_id !== $userId) {
                throw new DomainException('Non è il turno di questo player.');
            }

            $remaining = $state['remaining'][(int) $currentPlayer->user_id];
            $newRemaining = $remaining - $score;
            $isDouble = $multiplier === 2;
            $isBust = $newRemaining < 0 || $newRemaining === 1 || ($newRemaining === 0 && !$isDouble);

            $throw = new DartThrow([
                'match_id' => $matchId,
                'user_id' => $userId,
                'sector' => $sector,
                'multiplier' => $multiplier,
                'score' => $score,
            ]);
            if (!$throw->save()) {
                throw new DomainException('Impossibile registrare il tiro: ' . implode(' ', $throw->getFirstErrors()));
            }

            $currentPlayer->score = $isBust ? $state['visitStartScore'] : $newRemaining;
            if (!$currentPlayer->save(false, ['score'])) {
                throw new DomainException('Impossibile aggiornare il punteggio del player.');
            }

            if ($newRemaining === 0 && $isDouble) {
                $changed = DartsMatch::updateAll(
                    ['status' => MatchStatus::FINISHED->value, 'ended_at' => date('Y-m-d H:i:s')],
                    ['id' => $matchId, 'status' => MatchStatus::RUNNING->value],
                );
                if ($changed !== 1) {
                    throw new DomainException('Impossibile terminare automaticamente la partita.');
                }
            }

            $transaction->commit();
            return $throw;
        } catch (\Throwable $exception) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $exception;
        }
    }

    private function lockMatch(int $matchId): DartsMatch
    {
        $lockedRow = Yii::$app->db->createCommand(
            'SELECT id FROM {{%tbl_matches}} WHERE id = :id FOR UPDATE',
            [':id' => $matchId],
        )->queryOne();
        if ($lockedRow === false) {
            throw new DomainException('Partita di Freccette non trovata.');
        }

        $match = DartsMatch::findDarts()
            ->andWhere(['{{%tbl_matches}}.id' => $matchId])
            ->one();
        if ($match === null) {
            throw new DomainException('Partita di Freccette non trovata.');
        }

        return $match;
    }

    private function playersQuery(int $matchId): ActiveQuery
    {
        return MatchPlayer::find()
            ->where(['match_id' => $matchId])
            ->orderBy(['turn_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    private function throwsQuery(int $matchId): ActiveQuery
    {
        return DartThrow::find()->where(['match_id' => $matchId])->orderBy(['id' => SORT_ASC]);
    }

    /** Ricostruisce visita e turno dai tiri già persistiti. */
    private function replay(array $players, array $throws): array
    {
        if ($players === []) {
            throw new DomainException('La partita non ha partecipanti.');
        }

        $remaining = [];
        foreach ($players as $player) {
            $remaining[(int) $player->user_id] = DartsThrowCalculator::STARTING_SCORE;
        }
        $currentIndex = 0;
        $dartsInVisit = 0;
        $visitStartScore = DartsThrowCalculator::STARTING_SCORE;
        $finished = false;

        foreach ($throws as $throw) {
            if ($finished) {
                throw new DomainException('La partita contiene tiri successivi alla chiusura; continua con una nuova partita.');
            }

            $player = $players[$currentIndex];
            if ((int) $throw->user_id !== (int) $player->user_id) {
                throw new DomainException('I tiri salvati non seguono le regole 501; continua con una nuova partita.');
            }

            $points = $this->calculator->calculate((int) $throw->sector, (int) $throw->multiplier);
            if ((int) $throw->score !== $points) {
                throw new DomainException('Un tiro salvato ha un punteggio incoerente; continua con una nuova partita.');
            }

            $userId = (int) $player->user_id;
            $newRemaining = $remaining[$userId] - $points;
            $isDouble = (int) $throw->multiplier === 2;
            $isBust = $newRemaining < 0 || $newRemaining === 1 || ($newRemaining === 0 && !$isDouble);

            if ($isBust) {
                $remaining[$userId] = $visitStartScore;
                $currentIndex = ($currentIndex + 1) % count($players);
                $dartsInVisit = 0;
                $visitStartScore = $remaining[(int) $players[$currentIndex]->user_id];
                continue;
            }

            $remaining[$userId] = $newRemaining;
            if ($newRemaining === 0 && $isDouble) {
                $finished = true;
                continue;
            }

            $dartsInVisit++;
            if ($dartsInVisit === 3) {
                $currentIndex = ($currentIndex + 1) % count($players);
                $dartsInVisit = 0;
                $visitStartScore = $remaining[(int) $players[$currentIndex]->user_id];
            }
        }

        foreach ($players as $player) {
            if ((int) $player->score !== $remaining[(int) $player->user_id]) {
                throw new DomainException('Il punteggio salvato non corrisponde alle regole 501; crea una nuova partita.');
            }
        }
        if ($finished) {
            throw new DomainException('La partita è già conclusa.');
        }

        return compact('remaining', 'currentIndex', 'dartsInVisit', 'visitStartScore');
    }
}
