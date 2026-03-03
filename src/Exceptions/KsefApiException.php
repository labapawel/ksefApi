<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Exceptions;

use Exception;

/**
 * Wyjątek rzucany gdy żądanie do API KSeF się nie powiedzie.
 */
class KsefApiException extends Exception
{
    public function __construct(
        string $message = 'Błąd API KSeF',
        int $code = 0,
        ?\Throwable $previous = null,
        private ?array $apiResponse = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Pobierz odpowiedź z API.
     *
     * @return array|null
     */
    public function getApiResponse(): ?array
    {
        return $this->apiResponse;
    }
}
