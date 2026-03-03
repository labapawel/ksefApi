<?php

declare(strict_types=1);

namespace Labap\KsefApi\Contracts;

interface KsefClientInterface
{
    public function authenticate(): array;

    public function sendInvoice(string $xmlContent): array;

    public function fetchInvoiceDetails(string $ksefNumber): array;
}
