<?php

declare(strict_types=1);

namespace app\services;

use DomainException;

/** Calcola i punti di un tiro di Freccette secondo la configurazione corrente. */
final class DartsThrowCalculator
{
    public const STARTING_SCORE = 501;

    public function calculate(int $sector, int $multiplier): int
    {
        if (!in_array($sector, [...range(1, 20), 25], true)) {
            throw new DomainException('Il settore deve essere compreso tra 1 e 20 oppure essere il bull (25).');
        }

        if ($multiplier < 1 || $multiplier > 3) {
            throw new DomainException('Il moltiplicatore deve essere compreso tra 1 e 3.');
        }

        if ($sector === 25 && $multiplier === 3) {
            throw new DomainException('Il bull vale 25 (singolo) o 50 (doppio); non esiste il triplo bull.');
        }

        return $sector * $multiplier;
    }
}
