<?php

declare(strict_types=1);

namespace app\commands;

use app\models\User;
use app\models\DartsMatch;
use app\enums\MatchStatus;
use app\services\DartsGameService;
use DomainException;
use yii\console\Controller;
use yii\console\ExitCode;

/** Strumenti locali per simulare la partecipazione e i tiri di Freccette. */
final class SimulatoreController extends Controller
{
    public function actionAddPlayer(int $matchId, string $username): int
    {
        try {
            $participant = (new DartsGameService())->addPlayer($matchId, $username);
            $player = User::findOne($participant->user_id);
            $this->stdout(sprintf("Player %s aggiunto alla partita %d, ordine turno %d.\n", $player?->username ?? $username, $matchId, $participant->turn_order));

            return ExitCode::OK;
        } catch (DomainException $exception) {
            $this->stderr($exception->getMessage() . "\n");

            return ExitCode::DATAERR;
        }
    }

    public function actionPlay(int $matchId): int
    {
        if (!$this->interactive) {
            $this->stderr("La simulazione dei tiri richiede una sessione interattiva.\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $service = new DartsGameService();
        $this->stdout("Simulazione Freccette. Inserisci 'q' al settore per uscire.\n");

        while (true) {
            try {
                $player = $service->getCurrentPlayer($matchId);
            } catch (DomainException $exception) {
                $this->stderr($exception->getMessage() . "\n");

                return ExitCode::DATAERR;
            }

            $user = User::findOne($player->user_id);
            $username = $user?->username ?? ('utente #' . $player->user_id);
            $sectorInput = trim($this->prompt("Turno di {$username} — settore (0=mancato, 1-20, 25; q per uscire): "));
            if (strtolower($sectorInput) === 'q') {
                return ExitCode::OK;
            }
            if (!preg_match('/\A\d+\z/', $sectorInput)) {
                $this->stderr("Inserisci un settore numerico valido.\n");
                continue;
            }

            if ((int) $sectorInput === 0) {
                $multiplierInput = '1';
            } else {
                $multiplierInput = trim($this->prompt('Moltiplicatore (1-3): '));
                if (!preg_match('/\A\d+\z/', $multiplierInput)) {
                    $this->stderr("Inserisci un moltiplicatore numerico valido.\n");
                    continue;
                }
            }

            try {
                $previousRemaining = (int) $player->score;
                $throw = $service->recordThrow($matchId, (int) $player->user_id, (int) $sectorInput, (int) $multiplierInput);
                $player->refresh();
                if ((int) $throw->score === 0) {
                    $this->stdout(sprintf("Freccetta mancata: 0 punti. Punti rimanenti di %s: %d.\n", $username, $player->score));
                } elseif ((int) $player->score >= $previousRemaining) {
                    $this->stdout(sprintf("Bust: visita annullata. Punti rimanenti di %s: %d.\n", $username, $player->score));
                } else {
                    $this->stdout(sprintf("Tiro registrato: %d punti. Punti rimanenti di %s: %d.\n", $throw->score, $username, $player->score));
                }

                $match = DartsMatch::findOne($matchId);
                if ($match !== null && (int) $match->status === MatchStatus::FINISHED->value) {
                    $this->stdout("Checkout valido: partita terminata.\n");
                    return ExitCode::OK;
                }
            } catch (DomainException $exception) {
                $this->stderr($exception->getMessage() . "\n");
                return ExitCode::DATAERR;
            }
        }
    }
}
