<?php

declare(strict_types=1);

namespace Laenutus\Auth;

use Exception;

final class AuthException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}
