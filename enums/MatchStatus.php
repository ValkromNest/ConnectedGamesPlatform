<?php

namespace app\enums;

enum MatchStatus: int
{
    case CREATED = 0;
    case OPEN = 1;
    case RUNNING = 2;
    case FINISHED = 3;

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Creata',
            self::OPEN => 'Aperta',
            self::RUNNING => 'In corso',
            self::FINISHED => 'Terminata',
        };
    }
}