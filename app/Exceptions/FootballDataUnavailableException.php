<?php

namespace App\Exceptions;

use RuntimeException;

class FootballDataUnavailableException extends RuntimeException
{
    public const USER_MESSAGE = 'Football data is temporarily unavailable. Please try again later.';

    public function __construct(?string $message = null)
    {
        parent::__construct($message ?: self::USER_MESSAGE);
    }
}
