<?php

declare(strict_types=1);

namespace darts;

use DomainException;

final class DartsThrowCalculator
{
    public const STARTING_SCORE = 501;

    public function calculate(int $sector, int $multiplier): int
    {
        if ($multiplier < 1 || $multiplier > 3) {
            throw new DomainException('Multiplier must be between 1 and 3.');
        }
        if ($sector === 0) {
            if ($multiplier !== 1) {
                throw new DomainException('A miss must use multiplier 1.');
            }
            return 0;
        }
        if (!in_array($sector, [...range(1, 20), 25], true)) {
            throw new DomainException('Sector must be 0, 1-20, or bull (25).');
        }
        if ($sector === 25 && $multiplier === 3) {
            throw new DomainException('There is no triple bull.');
        }
        return $sector * $multiplier;
    }
}
