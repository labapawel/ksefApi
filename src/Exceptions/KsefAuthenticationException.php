<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Exceptions;

use Exception;

/**
 * Wyjątek rzucany gdy logowanie do KSeF się nie powiedzie.
 */
class KsefAuthenticationException extends Exception
{
    public function __construct(
        string $message = 'Nie udało się zalogować do KSeF',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
