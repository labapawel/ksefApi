<?php

declare(strict_types=1);

namespace Labapawel\KsefApi\Clients;

class KsefInvoiceClient
{
    public function sendInvoice(string $accessToken, string $xmlContent): array
    {
        // TODO: implement interactive session open/send/close flow.
        return [
            'referenceNumber' => null,
            'status' => null,
        ];
    }

    public function fetchInvoiceDetails(string $accessToken, string $ksefNumber): array
    {
        // TODO: implement invoice detail download and decrypt flow.
        return [
            'ksefNumber' => $ksefNumber,
            'xml' => null,
        ];
    }
}
