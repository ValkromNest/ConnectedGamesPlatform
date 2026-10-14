<?php

declare(strict_types=1);

namespace app\services;

use RuntimeException;

final class DartsGameServiceException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode)
    {
        parent::__construct($message);
    }
}
